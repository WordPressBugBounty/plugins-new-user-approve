import { createRoot } from "react-dom/client";
import App from "./App";
import "./style.css";
import "./role-editor.css";

const container = document.getElementById("nua_dashboard_layout");
if (container) {
  const root = createRoot(container);
  root.render(<App />);
}
