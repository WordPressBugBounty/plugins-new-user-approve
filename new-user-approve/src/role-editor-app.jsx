import { createRoot } from "react-dom/client";
import { HashRouter, Route, Routes } from "react-router-dom";
import { ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import RoleEditorTabs from "./components/role-editor/role-editor-tabs";
import "./role-editor.css";

const RoleEditorApp = () => (
  <HashRouter>
    <ToastContainer position="bottom-right" />
    <Routes>
      <Route path="/action=role-editor/*" element={<RoleEditorTabs />} />
    </Routes>
  </HashRouter>
);

let roleEditorRoot = null;
let mountedSlot = null;

const mountRoleEditor = () => {
  if (!(window.location.hash || "").includes("action=role-editor")) {
    return;
  }
  const slot = document.getElementById("nua_role_editor_mount");
  if (!slot || slot === mountedSlot) {
    return;
  }
  if (roleEditorRoot) {
    roleEditorRoot.unmount();
    roleEditorRoot = null;
  }
  mountedSlot = slot;
  roleEditorRoot = createRoot(slot);
  roleEditorRoot.render(<RoleEditorApp />);
};

const start = () => {
  mountRoleEditor();
  window.addEventListener("hashchange", mountRoleEditor);
  const observer = new MutationObserver(mountRoleEditor);
  observer.observe(document.body, { childList: true, subtree: true });
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
