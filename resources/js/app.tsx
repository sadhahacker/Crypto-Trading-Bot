import ReactDOM from "react-dom/client"
import { BrowserRouter, Routes, Route } from "react-router-dom"
import Dashboard from "@/pages/Dashboard"
import MainLayout from "@/pages/Master/MainLayout"
import axios from "axios";
import BotListPage from "@/pages/BotListPage.tsx";
import SignalsPage from "@/pages/SignalsPage.tsx";
import ExchangeSettingsPage from "@/pages/Settings/ExchangeSettingsPage.tsx";

// @ts-ignore
const baseUrl = new URL(import.meta.env.VITE_APP_URL).pathname;
axios.defaults.baseURL = baseUrl

function App() {
    return (
        <BrowserRouter basename={baseUrl}>
            <Routes>
                <Route element={<MainLayout />}>
                    <Route index element={<Dashboard />} />
                    <Route path="dashboard" element={<Dashboard />} />
                    <Route path="bots" element={<BotListPage />} />
                    <Route path="exchange/settings" element={<ExchangeSettingsPage />} />
                    <Route path="bots/:botId/signals" element={<SignalsPage />} />
                </Route>
            </Routes>
        </BrowserRouter>
    )
}

const root = ReactDOM.createRoot(document.getElementById("app")!)
root.render(<App />)
