import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import DashboardLayout from "./components/DashboardLayout";
import Dashboard from "./pages/Dashboard";
import Users from "./pages/Users";
import UserDetail from "./pages/UserDetail";
import Withdrawals from "./pages/Withdrawals";
import Ranking from "./pages/Ranking";
import Settings from "./pages/Settings";
import AuditLogs from "./pages/AuditLogs";
import SystemSettings from "./pages/SystemSettings";
import Roulette from "./pages/Roulette";
import Notifications from "./pages/Notifications";
import Blacklist from "./pages/Blacklist";
import Security from "./pages/Security";
import Database from "./pages/Database";
import Monetag from "./pages/Monetag";
import AllowedInstallers from "./pages/AllowedInstallers";
import AppUpdate from "./pages/AppUpdate";

function Router() {
  return (
    <Switch>
      <Route path={"/"}>
        <DashboardLayout>
          <Dashboard />
        </DashboardLayout>
      </Route>
      
      <Route path={"/users"}>
        <DashboardLayout>
          <Users />
        </DashboardLayout>
      </Route>
      
      <Route path={"/users/:id"}>
        <DashboardLayout>
          <UserDetail />
        </DashboardLayout>
      </Route>
      
      <Route path={"/withdrawals"}>
        <DashboardLayout>
          <Withdrawals />
        </DashboardLayout>
      </Route>
      
      <Route path={"/ranking"}>
        <DashboardLayout>
          <Ranking />
        </DashboardLayout>
      </Route>
      
      <Route path={"/settings"}>
        <DashboardLayout>
          <Settings />
        </DashboardLayout>
      </Route>
      
      <Route path={"/system-settings"}>
        <DashboardLayout>
          <SystemSettings />
        </DashboardLayout>
      </Route>
      
      <Route path={"/app-update"}>
        <DashboardLayout>
          <AppUpdate />
        </DashboardLayout>
      </Route>
      
      <Route path={"/roulette"}>
        <DashboardLayout>
          <Roulette />
        </DashboardLayout>
      </Route>
      
      <Route path={"/notifications"}>
        <DashboardLayout>
          <Notifications />
        </DashboardLayout>
      </Route>
      
      <Route path={"/blacklist"}>
        <DashboardLayout>
          <Blacklist />
        </DashboardLayout>
      </Route>
      
      <Route path={"/security"}>
        <DashboardLayout>
          <Security />
        </DashboardLayout>
      </Route>
      
      <Route path={"/database"}>
        <DashboardLayout>
          <Database />
        </DashboardLayout>
      </Route>
      
      <Route path={"/monetag"}>
        <DashboardLayout>
          <Monetag />
        </DashboardLayout>
      </Route>
      
      <Route path={"/audit-logs"}>
        <DashboardLayout>
          <AuditLogs />
        </DashboardLayout>
      </Route>
      
      <Route path={"/installers"}>
        <DashboardLayout>
          <AllowedInstallers />
        </DashboardLayout>
      </Route>
      
      <Route path={"/404"} component={NotFound} />
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider defaultTheme="light">
        <TooltipProvider>
          <Toaster />
          <Router />
        </TooltipProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}

export default App;
