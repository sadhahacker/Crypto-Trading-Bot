"use client"

import * as React from "react"
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    getFilteredRowModel,
    SortingState,
    ColumnFiltersState,
    useReactTable,
} from "@tanstack/react-table"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import axios from "axios"
import { Badge } from "@/components/ui/badge"
import { useParams } from "react-router-dom"
import { ArrowUpDown, Search } from "lucide-react"

// Signal type
type Signal = {
    id: number
    timestamp: string
    open: number
    high: number
    low: number
    close: number
    volume: number
    signal: number
    prediction: number
    barsHeld: number
}

const columns: ColumnDef<Signal>[] = [
    { 
        accessorKey: "timestamp", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Timestamp
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="font-medium text-nowrap">{new Date(row.getValue("timestamp")).toLocaleString()}</div>
        )
    },
    { 
        accessorKey: "open", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Open
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-right font-mono">${row.getValue("open").toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
        )
    },
    { 
        accessorKey: "high", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    High
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-right font-mono">${row.getValue("high").toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
        )
    },
    { 
        accessorKey: "low", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Low
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-right font-mono">${row.getValue("low").toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
        )
    },
    { 
        accessorKey: "close", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Close
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-right font-mono">${row.getValue("close").toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
        )
    },
    { 
        accessorKey: "volume", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Volume
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-right font-mono">{row.getValue("volume").toLocaleString()}</div>
        )
    },
    { 
        accessorKey: "signal", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Signal
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-center">
                <Badge variant={row.getValue("signal") > 0 ? "success" : "destructive"} className="px-3 py-1">
                    {row.getValue("signal") > 0 ? "BUY" : "SELL"}
                </Badge>
            </div>
        )
    },
    { 
        accessorKey: "prediction", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Prediction
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-right font-mono">{(row.getValue("prediction") * 100).toFixed(2)}%</div>
        )
    },
    { 
        accessorKey: "barsHeld", 
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    className="px-0 hover:bg-transparent font-semibold"
                >
                    Bars Held
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            )
        },
        cell: ({ row }) => (
            <div className="text-right font-mono">{row.getValue("barsHeld")}</div>
        )
    },
]

export default function SignalsPage() {
    const { botId } = useParams<{ botId: string }>()
    const [signals, setSignals] = React.useState<Signal[]>([])
    const [botName, setBotName] = React.useState("")
    const [coin, setCoin] = React.useState("")
    const [loading, setLoading] = React.useState(false)
    const [page, setPage] = React.useState(1)
    const [totalPages, setTotalPages] = React.useState(1)
    
    // Table states
    const [sorting, setSorting] = React.useState<SortingState>([])
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([])
    const [globalFilter, setGlobalFilter] = React.useState("")

    // Fetch signals from backend
    const fetchSignals = async (pageNumber: number) => {
        if (!botId) return
        
        setLoading(true)
        try {
            // In a real implementation, this would fetch from an API endpoint
            // const res = await axios.get(`/api/bots/${botId}/signals?page=${pageNumber}&paginate=10`)
            
            // Mock data for demonstration
            const mockSignals: Signal[] = Array.from({ length: 10 }, (_, i) => ({
                id: i + 1,
                timestamp: new Date(Date.now() - i * 3600000).toISOString(),
                open: 45000 + i * 100,
                high: 45500 + i * 100,
                low: 44900 + i * 100,
                close: 45300 + i * 100,
                volume: 125000000 + i * 5000000,
                signal: i % 3 === 0 ? 1 : -1,
                prediction: 0.75 - (i * 0.05),
                barsHeld: 5 + (i % 4)
            }))
            
            setSignals(mockSignals)
            setTotalPages(5)
            
            // Set bot name and coin based on botId
            switch (botId) {
                case "1":
                    setBotName("Lorentzian Classification Bot")
                    setCoin("BTCUSDT")
                    break
                case "2":
                    setBotName("Swing Bot")
                    setCoin("ETHUSDT")
                    break
                case "3":
                    setBotName("Arbitrage Bot")
                    setCoin("BNBUSDT")
                    break
                default:
                    setBotName("Unknown Bot")
                    setCoin("BTCUSDT")
            }
        } catch (err) {
            console.error("Failed to fetch signals", err)
            setSignals([])
        } finally {
            setLoading(false)
        }
    }

    React.useEffect(() => {
        fetchSignals(page)
    }, [page, botId])

    const table = useReactTable({
        data: signals,
        columns,
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        state: {
            sorting,
            globalFilter,
        },
    })

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold">{botName}</h1>
                <p className="text-muted-foreground">Trading signals for {coin}</p>
            </div>
            
            <Card className="shadow-lg">
                <CardHeader className="border-b">
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <CardTitle className="text-xl">Trading Signals</CardTitle>
                            <p className="text-muted-foreground text-sm">Historical trading signals and predictions</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search signals..."
                                    value={globalFilter ?? ""}
                                    onChange={(event) => setGlobalFilter(event.target.value)}
                                    className="pl-8 w-full md:w-64"
                                />
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {loading ? (
                        <div className="flex justify-center py-12">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <Table className="min-w-full">
                                    <TableHeader className="bg-muted/50">
                                        {table.getHeaderGroups().map((headerGroup) => (
                                            <TableRow key={headerGroup.id}>
                                                {headerGroup.headers.map((header) => (
                                                    <TableHead 
                                                        key={header.id} 
                                                        className="text-xs font-semibold text-muted-foreground px-4 py-3"
                                                    >
                                                        {header.isPlaceholder
                                                            ? null
                                                            : flexRender(
                                                                  header.column.columnDef.header,
                                                                  header.getContext()
                                                              )}
                                                    </TableHead>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableHeader>
                                    <TableBody>
                                        {table.getRowModel().rows?.length ? (
                                            table.getRowModel().rows.map((row) => (
                                                <TableRow
                                                    key={row.id}
                                                    data-state={row.getIsSelected() && "selected"}
                                                    className="hover:bg-muted/50 border-b last:border-b-0"
                                                >
                                                    {row.getVisibleCells().map((cell) => (
                                                        <TableCell 
                                                            key={cell.id} 
                                                            className="px-4 py-3 align-top"
                                                        >
                                                            {flexRender(
                                                                cell.column.columnDef.cell,
                                                                cell.getContext()
                                                            )}
                                                        </TableCell>
                                                    ))}
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={columns.length}
                                                    className="h-24 text-center"
                                                >
                                                    No results found.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                            
                            <div className="border-t p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div className="text-sm text-muted-foreground">
                                    Showing {signals.length} of {signals.length} signals
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setPage(prev => Math.max(prev - 1, 1))}
                                        disabled={page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <div className="text-sm">
                                        Page {page} of {totalPages}
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setPage(prev => Math.min(prev + 1, totalPages))}
                                        disabled={page === totalPages}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}