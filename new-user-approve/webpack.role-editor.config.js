const { CleanWebpackPlugin } = require("clean-webpack-plugin");
const base = require("./webpack.config");

module.exports = {
  ...base,
  entry: {
    "role-editor": "./src/role-editor-app.jsx",
  },
  plugins: (base.plugins || []).filter(
    (plugin) => !(plugin instanceof CleanWebpackPlugin)
  ),
};
