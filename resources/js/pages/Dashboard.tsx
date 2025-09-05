"use client"

import { useState, useEffect } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { TrendingUp, TrendingDown, Wallet, BarChart3 } from "lucide-react"
import axios from "axios"

type AccountDetails = {
    balance: {
        total: number
        assets: Array<{
            currency: string
            amount: number
            value: number
        }>
    }
    positions: Array<{
        symbol: string
        side: string
        contracts: number
        entryPrice: number
        markPrice: number
        unrealizedPnl: number
        percentage: number
    }>
    performance: {
        today: number
        week: number
        month: number
    }
}

export default function Dashboard() {
    const [accountDetails, setAccountDetails] = useState<AccountDetails | null>(null)
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        fetchAccountDetails()
    }, [])

    const fetchAccountDetails = async () => {
        try {
            // In a real implementation, this would fetch from the API
            const res = await axios.get('/api/account/details')

            setAccountDetails(res.data.data)
        } catch (error) {
            console.error("Failed to fetch account details:", error)
        } finally {
            setLoading(false)
        }
    }

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
        )
    }

    // Ensure we have data before rendering
    if (!accountDetails) {
        return (
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="bg-muted/50 aspect-video rounded-xl" />
                    <div className="bg-muted/50 aspect-video rounded-xl" />
                    <div className="bg-muted/50 aspect-video rounded-xl" />
                </div>
                <div className="bg-muted/50 min-h-[100vh] flex-1 rounded-xl md:min-h-min" />
            </div>
        )
    }

    return (
        <div className="space-y-6 p-4">
            <div>
                <h1 className="text-3xl font-bold">Dashboard</h1>
                <p className="text-muted-foreground">Overview of your trading account</p>
            </div>

            {/* Account Summary */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total Balance</CardTitle>
                        <Wallet className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            ${accountDetails.balance.assets.reduce((sum, asset) => sum + asset.value, 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <p className="text-xs text-muted-foreground">All assets in USDT</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Today's P&L</CardTitle>
                        <TrendingUp className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className={`text-2xl font-bold ${accountDetails.performance.today >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                            {accountDetails.performance.today >= 0 ? '+' : ''}
                            {accountDetails.performance.today.toFixed(2)}%
                        </div>
                        <p className="text-xs text-muted-foreground">Current day performance</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">This Week</CardTitle>
                        <BarChart3 className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className={`text-2xl font-bold ${accountDetails.performance.week >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                            {accountDetails.performance.week >= 0 ? '+' : ''}
                            {accountDetails.performance.week.toFixed(2)}%
                        </div>
                        <p className="text-xs text-muted-foreground">Weekly performance</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">This Month</CardTitle>
                        <BarChart3 className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className={`text-2xl font-bold ${accountDetails.performance.month >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                            {accountDetails.performance.month >= 0 ? '+' : ''}
                            {accountDetails.performance.month.toFixed(2)}%
                        </div>
                        <p className="text-xs text-muted-foreground">Monthly performance</p>
                    </CardContent>
                </Card>
            </div>

            {/* Assets and Positions */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Assets */}
                <Card>
                    <CardHeader>
                        <CardTitle>Assets</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {accountDetails.balance.assets.length > 0 ? (
                            <div className="space-y-4">
                                {accountDetails.balance.assets.map((asset, index) => (
                                    <div key={index} className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/10 p-2 rounded-full">
                                                <Wallet className="h-4 w-4 text-primary" />
                                            </div>
                                            <div>
                                                <div className="font-medium">{asset.currency}</div>
                                                <div className="text-sm text-muted-foreground">
                                                    {asset.amount.toLocaleString(undefined, {
                                                        minimumFractionDigits: asset.currency === 'BTC' ? 4 : 2,
                                                        maximumFractionDigits: asset.currency === 'BTC' ? 4 : 2
                                                    })}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-medium">
                                                ${asset.value.toLocaleString(undefined, {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-muted-foreground">
                                No assets found
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Positions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Active Positions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {accountDetails.positions && accountDetails.positions.length > 0 ? (
                            <div className="space-y-4">
                                {accountDetails.positions.map((position, index) => (
                                    <div key={index} className="border rounded-lg p-4">
                                        <div className="flex justify-between items-start">
                                            <div>
                                                <div className="font-medium">{position.symbol}</div>
                                                <Badge
                                                    variant={position.side === 'long' ? 'success' : 'destructive'}
                                                    className="mt-1"
                                                >
                                                    {position.side.toUpperCase()}
                                                </Badge>
                                            </div>
                                            <div className={`text-right ${position.unrealizedPnl >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                                                <div className="font-medium">
                                                    {position.unrealizedPnl >= 0 ? '+' : ''}
                                                    ${Math.abs(position.unrealizedPnl).toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    })}
                                                </div>
                                                <div className="text-sm">
                                                    {position.percentage >= 0 ? '+' : ''}
                                                    {position.percentage.toFixed(2)}%
                                                </div>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2 mt-3 text-sm">
                                            <div>
                                                <div className="text-muted-foreground">Size</div>
                                                <div>
                                                    {position.contracts.toLocaleString(undefined, {
                                                        minimumFractionDigits: 4,
                                                        maximumFractionDigits: 4
                                                    })}
                                                </div>
                                            </div>
                                            <div>
                                                <div className="text-muted-foreground">Entry Price</div>
                                                <div>
                                                    ${position.entryPrice.toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    })}
                                                </div>
                                            </div>
                                            <div>
                                                <div className="text-muted-foreground">Mark Price</div>
                                                <div>
                                                    ${position.markPrice.toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    })}
                                                </div>
                                            </div>
                                            <div>
                                                <div className="text-muted-foreground">PNL</div>
                                                <div className={position.unrealizedPnl >= 0 ? 'text-green-500' : 'text-red-500'}>
                                                    {position.unrealizedPnl >= 0 ? '+' : ''}
                                                    ${Math.abs(position.unrealizedPnl).toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    })}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-muted-foreground">
                                No active positions
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
            
            {/* Raw Balance Data (for debugging) */}
            {accountDetails.raw_balance && (
                <Card>
                    <CardHeader>
                        <CardTitle>Raw Balance Data</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="text-xs overflow-auto max-h-96">
                            {JSON.stringify(accountDetails.raw_balance, null, 2)}
                        </pre>
                    </CardContent>
                </Card>
            )}
        </div>
    )
}
