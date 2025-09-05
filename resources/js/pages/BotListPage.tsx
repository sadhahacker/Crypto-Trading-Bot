"use client"

import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Play, PauseCircle, Bot, TrendingUp, TrendingDown } from "lucide-react"
import axios from "axios"
import { useNavigate } from "react-router-dom"
import { toast } from "sonner"

type BotType = {
    id: number
    name: string
    coin: string
    status: "running" | "stopped"
    profit?: number
}

export default function BotListPage() {
    const [bots, setBots] = useState<BotType[]>([])
    const [loading, setLoading] = useState(true)
    const navigate = useNavigate()

    useEffect(() => {
        fetchBots()
    }, [])

    const fetchBots = async () => {
        try {
            // In a real implementation, this would fetch from an API endpoint
            const mockBots: BotType[] = [
                {
                    id: 1,
                    name: "Lorentzian Classification Bot",
                    coin: "BTCUSDT",
                    status: "running",
                    profit: 12.5
                },
                {
                    id: 2,
                    name: "Swing Bot",
                    coin: "ETHUSDT",
                    status: "stopped",
                    profit: -2.3
                },
                {
                    id: 3,
                    name: "Arbitrage Bot",
                    coin: "BNBUSDT",
                    status: "running",
                    profit: 8.7
                },
            ]
            setBots(mockBots)
        } catch (error) {
            console.error("Failed to fetch bots:", error)
            toast.error("Failed to load bots")
        } finally {
            setLoading(false)
        }
    }

    const toggleBot = async (id: number) => {
        try {
            // In a real implementation, this would call an API endpoint
            // await axios.post(`/api/bots/${id}/toggle`)
            setBots((prev) =>
                prev.map((bot) =>
                    bot.id === id
                        ? {
                              ...bot,
                              status: bot.status === "running" ? "stopped" : "running",
                          }
                        : bot
                )
            )
            toast.success("Bot status updated")
        } catch (error) {
            console.error("Failed to toggle bot:", error)
            toast.error("Failed to update bot status")
        }
    }

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold">Trading Bots</h1>
                <p className="text-muted-foreground">Manage your automated trading bots</p>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {bots.map((bot) => (
                    <Card 
                        key={bot.id} 
                        className="shadow-lg hover:shadow-xl transition-all duration-300 rounded-xl overflow-hidden border border-border"
                    >
                        <CardHeader className="pb-3 bg-gradient-to-r from-primary/5 to-primary/10">
                            <div className="flex justify-between items-start">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 rounded-lg bg-primary/10">
                                        <Bot className="h-6 w-6 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-lg font-bold">{bot.name}</CardTitle>
                                        <Badge variant="outline" className="mt-1">
                                            {bot.coin}
                                        </Badge>
                                    </div>
                                </div>
                                <Badge 
                                    variant={bot.status === "running" ? "success" : "destructive"}
                                    className="rounded-full px-3 py-1 text-xs"
                                >
                                    {bot.status}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <div className="flex justify-between items-center mb-4">
                                <div className="text-sm text-muted-foreground">Profit/Loss</div>
                                {bot.profit !== undefined ? (
                                    <div className={`flex items-center gap-1 ${bot.profit >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                                        {bot.profit >= 0 ? (
                                            <TrendingUp className="h-4 w-4" />
                                        ) : (
                                            <TrendingDown className="h-4 w-4" />
                                        )}
                                        <span className="font-semibold">{Math.abs(bot.profit)}%</span>
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground">-</span>
                                )}
                            </div>
                            
                            <div className="flex gap-2 mt-4">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="flex-1"
                                    onClick={() => navigate(`/bots/${bot.id}/signals`)}
                                >
                                    View Signals
                                </Button>
                                <Button
                                    size="sm"
                                    className="flex-1"
                                    onClick={() => toggleBot(bot.id)}
                                >
                                    {bot.status === "running" ? (
                                        <>
                                            <PauseCircle className="h-4 w-4 mr-2" />
                                            Stop
                                        </>
                                    ) : (
                                        <>
                                            <Play className="h-4 w-4 mr-2" />
                                            Start
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
            
            {bots.length === 0 && (
                <Card className="text-center py-12">
                    <Bot className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                    <h3 className="text-lg font-medium mb-1">No bots found</h3>
                    <p className="text-muted-foreground">Get started by creating a new trading bot</p>
                </Card>
            )}
        </div>
    )
}