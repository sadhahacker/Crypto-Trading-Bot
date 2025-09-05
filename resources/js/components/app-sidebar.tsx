"use client"

import * as React from "react"
import {
    Bot,
    LayoutDashboard,
    PieChart,
    Settings2,
    Briefcase,
    User,
} from "lucide-react"

import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from "@/components/ui/sidebar"

// Sidebar data
const data = {
    user: {
        name: "Sadha",
        email: "me@example.com",
        avatar: "/avatars/avatar.png", // replace with your image
    },
    navMain: [
        {
            title: "Dashboard",
            url: "/dashboard",
            icon: LayoutDashboard,
            isActive: true,
        },
        {
            title: "Bots",
            icon: Bot,
            url: "/bots"
        },
        {
            title: "Portfolio",
            url: "/portfolio",
            icon: Briefcase,
            items: [
                { title: "My Investments", url: "/portfolio/investments" },
                { title: "Performance", url: "/portfolio/performance" },
            ],
        },
        {
            title: "Analytics",
            url: "/analytics",
            icon: PieChart,
        },
        {
            title: "Profile",
            url: "/profile",
            icon: User,
        },
        {
            title: "Settings",
            url: "/settings",
            icon: Settings2,
            items: [
                { title: "General", url: "/settings/general" },
                { title: "Billing", url: "/settings/billing" },
            ],
        },
    ],
}

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <div className="px-3 py-2 text-lg font-bold">📊 Portfolio App</div>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={data.navMain} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser user={data.user} />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    )
}
