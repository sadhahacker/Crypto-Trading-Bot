import { useEffect, useState } from "react";
import axios from "axios";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { Loader2 } from "lucide-react";

export default function ExchangeSettingsPage() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [settings, setSettings] = useState({
        exchange_name: "",
        api_key: "",
        api_secret: "",
        stoploss_from_account_balance: "",
        takeprofit_from_account_balance: "",
        stoploss_from_coin: "",
        takeprofit_from_coin: "",
        default_type: "",
    });

    useEffect(() => {
        axios
            .get("/api/exchange-settings")
            .then((res) => setSettings(res.data))
            .catch(() => toast.error("Failed to load settings"))
            .finally(() => setLoading(false));
    }, []);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSettings({ ...settings, [e.target.name]: e.target.value });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        axios
            .post("/api/exchange-settings", settings)
            .then(() => toast.success("Settings updated successfully"))
            .catch(() => toast.error("Failed to update settings"))
            .finally(() => setSaving(false));
    };

    if (loading) {
        return (
            <div className="flex h-screen items-center justify-center bg-gray-50">
                <Loader2 className="animate-spin mr-2 h-6 w-6 text-indigo-600" />
                Loading settings...
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 p-6 flex justify-center">
            <Card className="w-full max-w-4xl shadow-lg border border-gray-200 rounded-2xl">
                <CardHeader className="p-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-t-2xl text-white">
                    <CardTitle className="text-3xl font-bold">Exchange Settings</CardTitle>
                </CardHeader>
                <CardContent className="p-8 space-y-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Exchange Info */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="flex flex-col space-y-2">
                                <Label>Exchange Name</Label>
                                <Input
                                    name="exchange_name"
                                    value={settings.exchange_name}
                                    onChange={handleChange}
                                    placeholder="binance"
                                />
                            </div>
                            <div className="flex flex-col space-y-2">
                                <Label>Default Type</Label>
                                <Input
                                    name="default_type"
                                    value={settings.default_type}
                                    onChange={handleChange}
                                    placeholder="future"
                                />
                            </div>
                        </div>

                        {/* API Keys */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="flex flex-col space-y-2">
                                <Label>API Key</Label>
                                <Input
                                    name="api_key"
                                    value={settings.api_key}
                                    onChange={handleChange}
                                    placeholder="Enter API key"
                                />
                            </div>
                            <div className="flex flex-col space-y-2">
                                <Label>API Secret</Label>
                                <Input
                                    name="api_secret"
                                    value={settings.api_secret}
                                    onChange={handleChange}
                                    placeholder="Enter API secret"
                                    type="password"
                                />
                            </div>
                        </div>

                        {/* Risk Settings */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="flex flex-col space-y-2">
                                <Label>Stoploss From Account Balance (%)</Label>
                                <Input
                                    name="stoploss_from_account_balance"
                                    value={settings.stoploss_from_account_balance}
                                    onChange={handleChange}
                                />
                            </div>
                            <div className="flex flex-col space-y-2">
                                <Label>Takeprofit From Account Balance (%)</Label>
                                <Input
                                    name="takeprofit_from_account_balance"
                                    value={settings.takeprofit_from_account_balance}
                                    onChange={handleChange}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="flex flex-col space-y-2">
                                <Label>Stoploss From Coin (%)</Label>
                                <Input
                                    name="stoploss_from_coin"
                                    value={settings.stoploss_from_coin}
                                    onChange={handleChange}
                                />
                            </div>
                            <div className="flex flex-col space-y-2">
                                <Label>Takeprofit From Coin (%)</Label>
                                <Input
                                    name="takeprofit_from_coin"
                                    value={settings.takeprofit_from_coin}
                                    onChange={handleChange}
                                />
                            </div>
                        </div>

                        <div className="flex justify-end pt-4">
                            <Button
                                type="submit"
                                disabled={saving}
                                className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-6 py-3 font-semibold shadow-md"
                            >
                                {saving && <Loader2 className="animate-spin h-4 w-4" />}
                                {saving ? "Saving..." : "Save Settings"}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
