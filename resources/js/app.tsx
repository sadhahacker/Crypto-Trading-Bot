import ReactDOM from "react-dom/client"
import { BrowserRouter, Routes, Route } from "react-router-dom"
import Dashboard from "@/pages/Dashboard"
import MainLayout from "@/pages/Master/MainLayout"
import axios from "axios";
import BotListPage from "@/pages/BotListPage.tsx";
import SignalsPage from "@/pages/SignalsPage.tsx";

axios.defaults.baseURL = import.meta.env.VITE_APP_URL

function App() {
    return (
        <BrowserRouter basename="/bot/public">
            <Routes>
                <Route element={<MainLayout />}>
                    <Route index element={<Dashboard />} />
                    <Route path="dashboard" element={<Dashboard />} />
                    <Route path="bots" element={<BotListPage />} />
                    <Route path="bots/:botId/signals" element={<SignalsPage />} />
                </Route>
            </Routes>
        </BrowserRouter>
    )
}

const root = ReactDOM.createRoot(document.getElementById("app")!)
root.render(<App />)