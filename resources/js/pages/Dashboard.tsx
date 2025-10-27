"use client"

import { useState, useEffect } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Wallet } from "lucide-react"
import axios from "axios"

type Asset = {
    asset: string
    walletBalance: number
    availableBalance: number
}

type Position = {
    symbol: string
    side: string
    contracts: number
    entryPrice: number
    markPrice: number
    unrealizedPnl: number
    percentage: number
}

type AccountDetails = {
    totalWalletBalance: number
    availableBalance: number
    assets: Asset[]
    positions: Position[]
}

export default function Dashboard() {
    const [accountDetails, setAccountDetails] = useState<AccountDetails | null>(null)
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        fetchAccountDetails()
    }, [])

    const fetchAccountDetails = async () => {
        try {
            const res = await axios.get("/api/account/details")
            const data = res.data.data

            // Map and format assets
            const assets: Asset[] = data.assets
                .map((a: any) => ({
                    asset: a.asset,
                    walletBalance: parseFloat(a.walletBalance),
                    availableBalance: parseFloat(a.availableBalance),
                }))
                .filter((a: Asset) => a.walletBalance > 0) // optional: only non-zero balances

            setAccountDetails({
                totalWalletBalance: parseFloat(data.totalWalletBalance),
                availableBalance: parseFloat(data.availableBalance),
                assets,
                positions: data.positions || [],
            })
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

    if (!accountDetails) {
        return (
            <div className="text-center text-muted-foreground py-16">
                Failed to load account details
            </div>
        )
    }

    return (
        <div className="space-y-6 p-4">
            <div>
                <h1 className="text-3xl font-bold">Dashboard</h1>
                <p className="text-muted-foreground">Overview of your trading account</p>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader className="flex justify-between items-center pb-2">
                        <CardTitle className="text-sm font-medium">Total Balance</CardTitle>
                        <Wallet className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            ${accountDetails.totalWalletBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <p className="text-xs text-muted-foreground">All assets in USDT</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex justify-between items-center pb-2">
                        <CardTitle className="text-sm font-medium">Available Balance</CardTitle>
                        <Wallet className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            ${accountDetails.availableBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <p className="text-xs text-muted-foreground">Funds ready for trading</p>
                    </CardContent>
                </Card>
            </div>

            {/* Assets Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Assets</CardTitle>
                </CardHeader>
                <CardContent>
                    {accountDetails.assets.length ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {accountDetails.assets.map((asset, idx) => (
                                <div key={idx} className="flex justify-between items-center border p-4 rounded-lg hover:shadow-md transition">
                                    <div>
                                        <div className="font-medium">{asset.asset}</div>
                                        <div className="text-sm text-muted-foreground">
                                            {asset.walletBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 })}
                                        </div>
                                    </div>
                                    <div className="text-right font-medium">
                                        ${asset.availableBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center text-muted-foreground py-8">No assets available</div>
                    )}
                </CardContent>
            </Card>

            {/* Positions Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Active Positions</CardTitle>
                </CardHeader>
                <CardContent>
                    {accountDetails.positions.length ? (
                        <div className="space-y-4">
                            {accountDetails.positions.map((pos, idx) => (
                                <div key={idx} className="border rounded-lg p-4 hover:shadow-md transition">
                                    <div className="flex justify-between items-center">
                                        <div>
                                            <div className="font-medium">{pos.symbol}</div>
                                            <Badge variant={pos.side === 'long' ? 'success' : 'destructive'} className="mt-1">
                                                {pos.side.toUpperCase()}
                                            </Badge>
                                        </div>
                                        <div className={`text-right font-medium ${pos.unrealizedPnl >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                                            {pos.unrealizedPnl >= 0 ? '+' : ''}${pos.unrealizedPnl.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3 text-sm">
                                        <div>
                                            <div className="text-muted-foreground">Contracts</div>
                                            <div>{pos.contracts}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">Entry Price</div>
                                            <div>${pos.entryPrice}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">Mark Price</div>
                                            <div>${pos.markPrice}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">PNL</div>
                                            <div className={pos.unrealizedPnl >= 0 ? 'text-green-500' : 'text-red-500'}>
                                                {pos.unrealizedPnl >= 0 ? '+' : ''}${pos.unrealizedPnl.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center text-muted-foreground py-8">No active positions</div>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
