import React, { useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Tabs } from "@mui/base/Tabs";
import { TabsList } from "@mui/base/TabsList";
import { TabPanel } from "@mui/base/TabPanel";
import { Tab } from "@mui/base/Tab";
import { Routes, Route, useNavigate, useLocation } from "react-router-dom";
import RoleEditor from "./role-editor";
import RolesList from "./roles-list";

const RoleEditorTabs = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const currentTab = location.pathname.split("/")[2] || "tab=roles";

    useEffect(() => {
        if (!location.pathname.split("/")[2]) {
            navigate("tab=roles");
        }
    }, []);

    const handleTabChange = (event, newTab) => {
        navigate(newTab);
    };

    return (
        <Tabs
            className="role-editor-subtabs"
            value={currentTab}
            onChange={handleTabChange}
        >
            <TabsList className="role-editor-subtabs-list setting-main-tabpanel">
                <Tab
                    value="tab=roles"
                    className={currentTab === "tab=roles" ? "nua_active_subtab" : ""}
                >
                    {__("Roles", "new-user-approve")}
                </Tab>
                <Tab
                    value="tab=capabilities"
                    className={currentTab === "tab=capabilities" ? "nua_active_subtab" : ""}
                >
                    {__("Capabilities", "new-user-approve")}
                </Tab>
            </TabsList>

            <Routes>
                <Route
                    path="tab=roles"
                    element={
                        <TabPanel value="tab=roles" className="roles-tabpanel">
                            <RolesList />
                        </TabPanel>
                    }
                />
                <Route
                    path="tab=capabilities"
                    element={
                        <TabPanel value="tab=capabilities" className="roles-tabpanel">
                            <RoleEditor />
                        </TabPanel>
                    }
                />
            </Routes>
        </Tabs>
    );
};

export default RoleEditorTabs;
