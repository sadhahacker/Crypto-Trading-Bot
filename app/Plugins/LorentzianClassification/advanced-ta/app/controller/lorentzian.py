import websocket
import json
import pandas as pd
import sqlite3
import threading
import time
import sys
import requests
from datetime import datetime
from collections import deque
from advanced_ta import LorentzianClassification
from ta.volume import money_flow_index as MFI
import os

class LorentzianWebSocketUpdater:
    def __init__(self, symbol, interval, sqlite_path, buffer_size=100):
        self.symbol = symbol.lower()
        self.interval = interval
        self.sqlite_path = sqlite_path
        self.table_name = "lorentzian_results"

        # Buffer for collecting klines before bulk processing
        self.kline_buffer = deque(maxlen=buffer_size)
        self.buffer_size = buffer_size

        # Threading locks
        self.lock = threading.Lock()
        self.running = True

        # WebSocket connection
        self.ws = None

        # Store latest data for Lorentzian calculation
        self.historical_data = pd.DataFrame()
        self.min_historical_bars = 500  # Window size to keep in DB

        print(f"Initializing WebSocket updater for {symbol} ({interval})")

    def get_historical_data(self, limit=1000):
        """Fetch initial historical data via REST API"""
        url = "https://api.binance.com/api/v3/klines"
        params = {
            "symbol": self.symbol.upper(),
            "interval": self.interval,
            "limit": limit
        }

        print(f"Fetching {limit} historical candles...")
        try:
            response = requests.get(url, params=params, timeout=10)
            response.raise_for_status()
            data = response.json()

            if not data:
                raise ValueError("Received empty data from Binance API.")

            df = pd.DataFrame(data, columns=[
                'timestamp', 'open', 'high', 'low', 'close', 'volume',
                'close_time', 'quote_asset_volume', 'trades',
                'taker_base_vol', 'taker_quote_vol', 'ignore'
            ])

            df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
            df.set_index('timestamp', inplace=True)

            numeric_cols = ['open', 'high', 'low', 'close', 'volume']
            df[numeric_cols] = df[numeric_cols].apply(pd.to_numeric, errors='coerce')
            df.dropna(subset=numeric_cols, inplace=True)

            print(f"Fetched {len(df)} historical candles successfully.")
            return df[['open', 'high', 'low', 'close', 'volume']]

        except Exception as e:
            raise Exception(f"Error fetching historical data: {e}") from e

    def setup_database(self):
        """Initialize database with historical data"""
        try:
            conn = sqlite3.connect(self.sqlite_path)
            cursor = conn.cursor()

            # Check if table exists
            cursor.execute("""
                SELECT name FROM sqlite_master 
                WHERE type='table' AND name=?
            """, (self.table_name,))
            table_exists = cursor.fetchone()

            if table_exists:
                # Count records
                cursor.execute(f"SELECT COUNT(*) FROM {self.table_name}")
                count = cursor.fetchone()[0]

                if count >= self.min_historical_bars:
                    print(f"Found {count} existing records in database.")
                    df = pd.read_sql(
                        f"""SELECT * FROM {self.table_name} 
                            ORDER BY timestamp DESC 
                            LIMIT {self.min_historical_bars}""",
                        conn,
                        parse_dates=['timestamp'],
                        index_col='timestamp'
                    )
                    self.historical_data = df.sort_index()
                    conn.close()
                    return True

            conn.close()

            # Fetch and store initial data
            print("Setting up database with historical data...")
            df = self.get_historical_data(self.min_historical_bars)
            self.historical_data = df.copy()

            result_df = self.calculate_lorentzian_features(df)
            if result_df is None:
                return False

            result_df.reset_index(inplace=True)
            result_df['timestamp'] = result_df['timestamp'].astype(str)

            conn = sqlite3.connect(self.sqlite_path)
            # Create table with PRIMARY KEY
            conn.execute(f"""
                CREATE TABLE IF NOT EXISTS {self.table_name} (
                    timestamp TEXT PRIMARY KEY
                )
            """)
            result_df.to_sql(self.table_name, conn, if_exists="replace", index=False)
            conn.close()

            print(f"Database initialized with {len(result_df)} records.")
            return True

        except Exception as e:
            print(f"Error setting up database: {e}")
            return False

    def calculate_lorentzian_features(self, df):
        """Calculate Lorentzian Classification features"""
        try:
            if len(df) < 50:
                return None

            lc = LorentzianClassification(
                df,
                features=[
                    LorentzianClassification.Feature("RSI", 14, 2),
                    LorentzianClassification.Feature("WT", 10, 11),
                    LorentzianClassification.Feature("CCI", 20, 2),
                    LorentzianClassification.Feature("ADX", 20, 2),
                    LorentzianClassification.Feature("RSI", 9, 2),
                    MFI(df['high'], df['low'], df['close'], df['volume'], window=14)
                ],
                settings=LorentzianClassification.Settings(
                    source=df['close'],
                    neighborsCount=8,
                    maxBarsBack=2000,
                    useDynamicExits=False
                ),
                filterSettings=LorentzianClassification.FilterSettings(
                    useVolatilityFilter=True,
                    useRegimeFilter=True,
                    useAdxFilter=False,
                    regimeThreshold=-0.1,
                    adxThreshold=20,
                    kernelFilter=LorentzianClassification.KernelFilter(
                        useKernelSmoothing=False,
                        lookbackWindow=8,
                        relativeWeight=8.0,
                        regressionLevel=25,
                        crossoverLag=2,
                    )
                )
            )
            return lc.df
        except Exception as e:
            print(f"Error calculating Lorentzian Classification: {e}")
            return None

    def on_message(self, ws, message):
        """Handle incoming WebSocket messages"""
        try:
            data = json.loads(message)
            if 'k' not in data:
                return

            kline = data['k']
            if not kline['x']:
                return

            kline_data = {
                'timestamp': pd.to_datetime(kline['t'], unit='ms'),
                'open': float(kline['o']),
                'high': float(kline['h']),
                'low': float(kline['l']),
                'close': float(kline['c']),
                'volume': float(kline['v'])
            }

            with self.lock:
                self.kline_buffer.append(kline_data)
                print(f"Received kline: {kline_data['timestamp']} | Close: {kline_data['close']}")
                if len(self.kline_buffer) >= 5:
                    self.process_buffer()

        except Exception as e:
            print(f"Error processing WebSocket message: {e}")

    def process_buffer(self):
        """Process accumulated klines and update database"""
        if not self.kline_buffer:
            return

        try:
            print(f"Processing {len(self.kline_buffer)} buffered klines...")
            new_data = pd.DataFrame(list(self.kline_buffer))
            new_data.set_index('timestamp', inplace=True)
            self.kline_buffer.clear()

            combined_data = pd.concat([self.historical_data, new_data])
            combined_data = combined_data[~combined_data.index.duplicated(keep='last')]
            combined_data.sort_index(inplace=True)

            if len(combined_data) > self.min_historical_bars * 2:
                combined_data = combined_data.tail(self.min_historical_bars * 2)

            result_df = self.calculate_lorentzian_features(combined_data)
            if result_df is None:
                return

            result_df.reset_index(inplace=True)
            result_df['timestamp'] = result_df['timestamp'].astype(str)

            new_timestamps = new_data.index.astype(str)
            new_results = result_df[result_df['timestamp'].isin(new_timestamps)].copy()

            if not new_results.empty:
                conn = sqlite3.connect(self.sqlite_path)
                new_results.to_sql(self.table_name, conn, if_exists="append", index=False)

                # Keep only last N rows
                conn.execute(f"""
                    DELETE FROM {self.table_name}
                    WHERE timestamp NOT IN (
                        SELECT timestamp FROM {self.table_name}
                        ORDER BY timestamp DESC
                        LIMIT {self.min_historical_bars}
                    )
                """)
                conn.commit()
                conn.close()

                print(f"Added {len(new_results)} new records (keeping {self.min_historical_bars} latest).")
                self.historical_data = combined_data.tail(self.min_historical_bars).copy()

        except Exception as e:
            print(f"Error processing buffer: {e}")

    def on_error(self, ws, error):
        print(f"WebSocket error: {error}")

    def on_close(self, ws, close_status_code, close_msg):
        print("WebSocket connection closed")
        if self.running:
            print("Reconnecting in 5 seconds...")
            time.sleep(5)
            self.connect()

    def on_open(self, ws):
        print(f"WebSocket connected for {self.symbol.upper()} {self.interval}")
        subscribe_message = {
            "method": "SUBSCRIBE",
            "params": [f"{self.symbol}@kline_{self.interval}"],
            "id": 1
        }
        ws.send(json.dumps(subscribe_message))
        print(f"Subscribed to {self.symbol}@kline_{self.interval}")

    def connect(self):
        if not self.running:
            return
        websocket_url = "wss://stream.binance.com:9443/ws"
        self.ws = websocket.WebSocketApp(
            websocket_url,
            on_open=self.on_open,
            on_message=self.on_message,
            on_error=self.on_error,
            on_close=self.on_close
        )
        self.ws.run_forever()

    def start_buffer_processor(self):
        def process_loop():
            while self.running:
                time.sleep(30)
                with self.lock:
                    if self.kline_buffer:
                        self.process_buffer()
        thread = threading.Thread(target=process_loop, daemon=True)
        thread.start()
        return thread

    def run(self):
        try:
            if not self.setup_database():
                print("Failed to setup database")
                return False
            self.start_buffer_processor()
            print("Starting WebSocket connection...")
            self.connect()
        except KeyboardInterrupt:
            print("\nShutting down...")
            self.stop()
        except Exception as e:
            print(f"Error in run loop: {e}")
            return False

    def stop(self):
        self.running = False
        if self.ws:
            self.ws.close()
        with self.lock:
            if self.kline_buffer:
                self.process_buffer()
        print("WebSocket updater stopped.")

def main():
    if len(sys.argv) != 4:
        print("Usage: python websocket_updater.py SYMBOL INTERVAL SQLITE_PATH")
        sys.exit(1)

    symbol = sys.argv[1]
    interval = sys.argv[2]
    sqlite_path = sys.argv[3]
    os.makedirs(os.path.dirname(sqlite_path), exist_ok=True)

    updater = LorentzianWebSocketUpdater(symbol, interval, sqlite_path)
    try:
        updater.run()
    except KeyboardInterrupt:
        print("\nReceived interrupt signal")
    finally:
        updater.stop()

if __name__ == '__main__':
    main()
