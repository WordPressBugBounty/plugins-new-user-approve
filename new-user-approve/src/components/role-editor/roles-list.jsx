import React, { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { toast } from "react-toastify";
import Pagination from "@mui/material/Pagination";
import Stack from "@mui/material/Stack";
import Skeleton from "@mui/material/Skeleton";
import {
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Box,
    Paper,
    MenuItem,
    FormControl,
    Select,
    Typography,
    Button,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    IconButton,
} from "@mui/material";
import CloseIcon from "@mui/icons-material/Close";
import {
    re_get_roles,
    re_create_role,
    re_delete_role,
    re_rename_role,
} from "../../functions";
// ── Role color dot ─────────────────────────────────────────────────────────────
const ROLE_COLORS = [
    "#e74c3c", "#3498db", "#2ecc71", "#9b59b6",
    "#f39c12", "#1abc9c", "#e67e22", "#34495e",
];
const roleColor = (slug) => {
    let hash = 0;
    for (let i = 0; i < slug.length; i++) hash = slug.charCodeAt(i) + ((hash << 5) - hash);
    return ROLE_COLORS[Math.abs(hash) % ROLE_COLORS.length];
};

const autoSlug = (name) => name.toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");

// ── Main component ─────────────────────────────────────────────────────────────
const RolesList = () => {
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [totalRoles, setTotalRoles] = useState(0);
    const [search, setSearch] = useState("");
    const [searchLoading, setSearchLoading] = useState(true);

    // Sorting
    const [sortField, setSortField] = useState(null); // 'role' | 'users' | 'caps'
    const [sortDir, setSortDir] = useState('asc');

    const handleSort = (field) => {
        if (sortField === field) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortField(field);
            setSortDir('asc');
        }
    };

    const getSortedRoles = (list) => {
        if (!sortField) return list;
        return [...list].sort((a, b) => {
            let aVal, bVal;
            if (sortField === 'role') {
                aVal = a.display_name.toLowerCase();
                bVal = b.display_name.toLowerCase();
            } else if (sortField === 'users') {
                aVal = a.user_count ?? 0;
                bVal = b.user_count ?? 0;
            } else if (sortField === 'caps') {
                aVal = Object.keys(a.capabilities || {}).length;
                bVal = Object.keys(b.capabilities || {}).length;
            }
            if (aVal < bVal) return sortDir === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });
    };

    // Add role modal
    const [showAdd, setShowAdd] = useState(false);
    const [newName, setNewName] = useState("");
    const [newSlug, setNewSlug] = useState("");
    const [submitting, setSubmitting] = useState(false);

    // Delete confirm modal
    const [deleteModal, setDeleteModal] = useState(false);
    const [roleToDelete, setRoleToDelete] = useState(null);

    // Edit role modal
    const [editRole, setEditRole] = useState(null);
    const [editName, setEditName] = useState("");
    const [editSlug, setEditSlug] = useState("");

    const fetchRoles = async () => {
        try {
            setLoading(true);
            const res = await re_get_roles({ page, limit: rowsPerPage, search });
            if (res.data) {
                setRoles(res.data.roles || []);
                setTotalRoles(res.data.totals || 0);
            }
        } catch (error) {
            // handle silently
        } finally {
            setLoading(false);
            setSearchLoading(false);
        }
    };

    useEffect(() => {
        fetchRoles();
    }, [search, page, rowsPerPage]);

    const pageCount = Math.ceil(totalRoles / rowsPerPage);
    const startIndex = (page - 1) * rowsPerPage + 1;
    const endIndex = Math.min(page * rowsPerPage, totalRoles);

    const handleRowsPerPageChange = (event) => {
        setRowsPerPage(event.target.value);
        setPage(1);
    };

    const handleAdd = async () => {
        if (!newName.trim() || !newSlug.trim()) return;
        if (/^\d+$/.test(newName.trim()) || /^\d+$/.test(newSlug.trim())) {
            toast.error(__("Role name and slug must contain at least one letter.", "new-user-approve"), { position: 'bottom-right' });
            return;
        }
        try {
            setSubmitting(true);
            const res = await re_create_role({ display_name: newName.trim(), role_slug: newSlug.trim() });
            if (res.error) {
                toast.error(typeof res.error === "string" ? res.error : __("Failed to create role.", "new-user-approve"), { position: 'bottom-right' });
            } else {
                toast.success(__("Role created.", "new-user-approve"), { position: 'bottom-right' });
                setShowAdd(false); setNewName(""); setNewSlug("");
                fetchRoles();
            }
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async () => {
        if (!roleToDelete) return;
        try {
            setSubmitting(true);
            const res = await re_delete_role({ role_slug: roleToDelete.slug });
            if (res.error) {
                toast.error(typeof res.error === "string" ? res.error : __("Failed to delete role.", "new-user-approve"), { position: 'bottom-right' });
            } else {
                toast.success(__("Role deleted successfully.", "new-user-approve"), { position: 'bottom-right' });
                fetchRoles();
            }
            setDeleteModal(false);
            setRoleToDelete(null);
        } finally {
            setSubmitting(false);
        }
    };

    const openEdit = (role) => {
        setEditRole(role);
        setEditName(role.display_name);
        setEditSlug(role.slug);
    };

    const handleEdit = async () => {
        if (!editName.trim() || !editSlug.trim()) return;
        try {
            setSubmitting(true);
            const res = await re_rename_role({
                old_slug: editRole.slug,
                display_name: editName.trim(),
                new_slug: editSlug.trim(),
            });
            if (res.error) {
                toast.error(typeof res.error === "string" ? res.error : __("Failed to update role.", "new-user-approve"), { position: 'bottom-right' });
            } else {
                toast.success(__("Role updated.", "new-user-approve"), { position: 'bottom-right' });
                setEditRole(null);
                fetchRoles();
            }
        } finally {
            setSubmitting(false);
        }
    };

    let searchIcon = (
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M10 18C11.775 17.9998 13.4989 17.4056 14.897 16.312L19.293 20.708L20.707 19.294L16.311 14.898C17.4051 13.4997 17.9997 11.7755 18 10C18 5.589 14.411 2 10 2C5.589 2 2 5.589 2 10C2 14.411 5.589 18 10 18ZM10 4C13.309 4 16 6.691 16 10C16 13.309 13.309 16 10 16C6.691 16 4 13.309 4 10C4 6.691 6.691 4 10 4Z"
                fill="#9DADC1"
            />
        </svg>
    );

    return (
        <div>
            <div className="nua-roles-list-page">
                <div className="all_users_list_header">
                    <h2 className="users_list_title">{__("Roles", "new-user-approve")}</h2>

                    <div className="nua-header-filters">
                        <Box
                            className="nua-search-field-box"
                            component="form"
                            noValidate
                            autoComplete="off"
                            sx={{ width: "30ch", position: "relative" }}
                        >
                            <input
                                type="text"
                                className="nua-search-field"
                                placeholder={__("Search roles", "new-user-approve")}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    setPage(1);
                                    setSearchLoading(false);
                                }}
                            />
                            <div className="code-search-icon">{searchIcon}</div>
                            {searchLoading && (
                                <div className="new-user-approve-loading nua-search-loading">
                                    <div className="nua-spinner"></div>
                                </div>
                            )}
                        </Box>

                        <Stack direction="row" justifyContent="space-between" alignItems="center">
                            <span className="selectSpan" style={{ marginRight: 8 }}>
                                {__("Show:", "new-user-approve")}
                            </span>
                            <FormControl size="small" sx={{ minWidth: 120 }}>
                                <Select value={rowsPerPage} onChange={handleRowsPerPageChange}>
                                    <MenuItem value={10}>{__("10", "new-user-approve")}</MenuItem>
                                    <MenuItem value={20}>{__("20", "new-user-approve")}</MenuItem>
                                    <MenuItem value={50}>{__("50", "new-user-approve")}</MenuItem>
                                    <MenuItem value={100}>{__("100", "new-user-approve")}</MenuItem>
                                </Select>
                            </FormControl>
                            <span className="selectSpan" style={{ marginLeft: 8 }}>
                                {__("entries", "new-user-approve")}
                            </span>
                        </Stack>

                        <button className="nua-btn save-changes" onClick={() => setShowAdd(true)}>
                            <span><svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 5.71429H5.71429V10H4.28571V5.71429H0V4.28571H4.28571V0H5.71429V4.28571H10V5.71429Z" fill="#456644" />
                            </svg>
                            </span> {__("Add New role", "new-user-approve")}
                        </button>
                    </div>
                </div>

                {/* ── Table ── */}
                <TableContainer className="all_users_tbl_container usersTable nua-roles-table-container" component={Paper}>
                    <Table sx={{ minWidth: 650 }}>
                        <TableHead>
                            <TableRow sx={{ backgroundColor: "#FAFAFA" }}>
                                <TableCell>
                                    <span className="nua-sort-header" onClick={() => handleSort('role')}>
                                        {__("Role", "new-user-approve")}
                                        <span className="nua-sort-icon">
                                            {sortField === 'role' ? (sortDir === 'asc' ? '↑' : '↓') : '↑↓'}
                                        </span>
                                    </span>
                                </TableCell>
                                <TableCell>{__("Slug", "new-user-approve")}</TableCell>
                                <TableCell align="center">
                                    <span className="nua-sort-header" onClick={() => handleSort('users')}>
                                        {__("Users", "new-user-approve")}
                                        <span className="nua-sort-icon">
                                            {sortField === 'users' ? (sortDir === 'asc' ? '↑' : '↓') : '↑↓'}
                                        </span>
                                    </span>
                                </TableCell>
                                <TableCell align="center">
                                    <span className="nua-sort-header" onClick={() => handleSort('caps')}>
                                        {__("Capabilities", "new-user-approve")}
                                        <span className="nua-sort-icon">
                                            {sortField === 'caps' ? (sortDir === 'asc' ? '↑' : '↓') : '↑↓'}
                                        </span>
                                    </span>
                                </TableCell>
                                <TableCell align="right">{__("Actions", "new-user-approve")}</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {loading ? (
                                [...Array(5)].map((_, i) => (
                                    <TableRow key={i}>
                                        <TableCell><Skeleton variant="text" width="60%" /></TableCell>
                                        <TableCell><Skeleton variant="text" width="80%" /></TableCell>
                                        <TableCell><Skeleton variant="text" width={40} sx={{ margin: "0 auto" }} /></TableCell>
                                        <TableCell><Skeleton variant="text" width={40} sx={{ margin: "0 auto" }} /></TableCell>
                                        <TableCell><Skeleton variant="text" width={80} sx={{ marginLeft: "auto" }} /></TableCell>
                                    </TableRow>
                                ))
                            ) : roles.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} align="center" sx={{ padding: "40px", color: "#888" }}>
                                        {__("No roles found.", "new-user-approve")}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                getSortedRoles(roles).map((role) => {
                                    const capCount = Object.keys(role.capabilities || {}).length;
                                    return (
                                        <TableRow key={role.slug} className="nua-roles-table-row">
                                            {/* Role name + badge */}
                                            <TableCell>
                                                <div className="nua-roles-table-role-cell">
                                                    <span
                                                        className="nua-roles-table-dot"
                                                        style={{ background: roleColor(role.slug) }}
                                                    />
                                                    <div>
                                                        <span className="nua-roles-table-role-name">{role.display_name}</span>
                                                        {!role.is_custom && (
                                                            <span className="nua-roles-table-badge nua-roles-badge-core">
                                                                {__("CORE", "new-user-approve")}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Slug */}
                                            <TableCell>
                                                <code className="nua-roles-table-slug">{role.slug}</code>
                                            </TableCell>

                                            {/* Users */}
                                            <TableCell align="center">
                                                <span className="nua-roles-table-count">{role.user_count ?? "—"}</span>
                                            </TableCell>

                                            {/* Capabilities */}
                                            <TableCell align="center">
                                                <span className="nua-roles-table-count">{capCount}</span>
                                            </TableCell>

                                            {/* Actions */}
                                            <TableCell align="right">
                                                <div className="nua-roles-table-actions">
                                                    {/* Edit */}
                                                    <button
                                                        className="nua-re-btn-icon"
                                                        title={__("Edit", "new-user-approve")}
                                                        onClick={() => openEdit(role)}
                                                    >
                                                        <span className="actionsIcon">
                                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M9.99999 4.00002L12 6.00002M8.66666 13.3334H14M3.33332 10.6667L2.66666 13.3334L5.33332 12.6667L13.0573 4.94269C13.3073 4.69265 13.4477 4.35357 13.4477 4.00002C13.4477 3.64647 13.3073 3.30739 13.0573 3.05736L12.9427 2.94269C12.6926 2.69273 12.3535 2.55231 12 2.55231C11.6464 2.55231 11.3074 2.69273 11.0573 2.94269L3.33332 10.6667Z" stroke="#242424" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                                            </svg>
                                                        </span>
                                                    </button>

                                                    {/* Delete (only custom roles) */}
                                                    {role.is_custom && (
                                                        <button
                                                            className="nua-re-btn-icon"
                                                            title={__("Delete", "new-user-approve")}
                                                            onClick={() => { setRoleToDelete(role); setDeleteModal(true); }}
                                                        >
                                                            <span className="actionsIcon">
                                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M5.46667 2.75198H5.33334C5.40667 2.75198 5.46667 2.69438 5.46667 2.62398V2.75198ZM5.46667 2.75198H10.5333V2.62398C10.5333 2.69438 10.5933 2.75198 10.6667 2.75198H10.5333V3.90398H11.7333V2.62398C11.7333 2.05918 11.255 1.59998 10.6667 1.59998H5.33334C4.74501 1.59998 4.26667 2.05918 4.26667 2.62398V3.90398H5.46667V2.75198ZM13.8667 3.90398H2.13334C1.83834 3.90398 1.60001 4.13278 1.60001 4.41598V4.92798C1.60001 4.99838 1.66001 5.05598 1.73334 5.05598H2.74001L3.15167 13.424C3.17834 13.9696 3.64834 14.4 4.21667 14.4H11.7833C12.3533 14.4 12.8217 13.9712 12.8483 13.424L13.26 5.05598H14.2667C14.34 5.05598 14.4 4.99838 14.4 4.92798V4.41598C14.4 4.13278 14.1617 3.90398 13.8667 3.90398ZM11.655 13.248H4.34501L3.94167 5.05598H12.0583L11.655 13.248Z" fill="#BD5E5E" />
                                                                </svg>
                                                            </span>
                                                        </button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>

                {roles.length > 0 && (
                    <Stack
                        spacing={2}
                        alignItems="center"
                        mt={2}
                        className="nua-table-pagination"
                    >
                        <Pagination
                            count={Math.max(1, pageCount)}
                            page={page}
                            onChange={(event, value) => setPage(value)}
                            variant="outlined"
                            shape="rounded"
                            className="nua-nav-pagination"
                        />
                        <Typography variant="body2" className="nua-table-total-data">
                            {`${startIndex}\u2013${endIndex} of ${totalRoles}`}
                            <span style={{ marginLeft: 5 }}>
                                {__("entries", "new-user-approve")}
                            </span>
                        </Typography>
                    </Stack>
                )}

                {/* ── Modal: Delete Role ── */}
                <Dialog
                    open={deleteModal}
                    onClose={() => { setDeleteModal(false); setRoleToDelete(null); }}
                    maxWidth="xs"
                    fullWidth={true}
                    className="openNuaModal delete-inv-modal openNuaTabsModal NuaModal"
                >
                    <DialogTitle className="delete-title"></DialogTitle>
                    <IconButton
                        aria-label="close"
                        className="nua-modal-close"
                        onClick={() => { setDeleteModal(false); setRoleToDelete(null); }}
                        sx={(theme) => ({
                            position: "absolute",
                            right: 8,
                            top: 12,
                            color: theme.palette.grey[500],
                        })}
                    >
                        <CloseIcon />
                    </IconButton>
                    <DialogContent dividers className="delete-confirmation">
                        <div className="sure-icon">
                            <svg width="68" height="68" viewBox="0 0 68 68" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="68" height="68" rx="34" fill="#FFEDED" />
                                <path d="M49.6977 43.2809L36.2266 18.3269C35.2718 16.5577 32.7282 16.5577 31.7726 18.3269L18.3023 43.2809C18.095 43.665 17.991 44.0962 18.0006 44.5323C18.0102 44.9684 18.133 45.3946 18.357 45.7693C18.581 46.1439 18.8985 46.4542 19.2786 46.67C19.6587 46.8857 20.0884 46.9994 20.5257 47H47.4703C47.908 47.0001 48.3382 46.8868 48.7188 46.6714C49.0995 46.456 49.4176 46.1457 49.642 45.771C49.8665 45.3962 49.9896 44.9697 49.9994 44.5333C50.0091 44.0968 49.9052 43.6653 49.6977 43.2809ZM34 43.1367C33.6873 43.1367 33.3817 43.0442 33.1217 42.8709C32.8618 42.6976 32.6591 42.4514 32.5395 42.1632C32.4198 41.8751 32.3885 41.558 32.4495 41.2522C32.5105 40.9463 32.6611 40.6653 32.8822 40.4448C33.1033 40.2242 33.3849 40.0741 33.6916 40.0132C33.9982 39.9524 34.3161 39.9836 34.605 40.1029C34.8938 40.2223 35.1407 40.4244 35.3144 40.6837C35.4881 40.943 35.5808 41.2479 35.5808 41.5598C35.5808 41.978 35.4143 42.3791 35.1178 42.6748C34.8214 42.9705 34.4193 43.1367 34 43.1367ZM35.7168 27.2773L35.2631 36.8962C35.2631 37.2308 35.1298 37.5516 34.8927 37.7882C34.6555 38.0248 34.3338 38.1577 33.9984 38.1577C33.663 38.1577 33.3413 38.0248 33.1042 37.7882C32.867 37.5516 32.7337 37.2308 32.7337 36.8962L32.28 27.2812C32.2699 27.0514 32.3061 26.822 32.3867 26.6065C32.4673 26.391 32.5906 26.194 32.7492 26.027C32.9078 25.8601 33.0984 25.7267 33.3098 25.6348C33.5212 25.5429 33.7489 25.4945 33.9795 25.4922H33.996C34.2282 25.4921 34.4579 25.5389 34.6714 25.6298C34.8849 25.7207 35.0777 25.8538 35.2382 26.021C35.3988 26.1883 35.5236 26.3862 35.6053 26.603C35.687 26.8197 35.7239 27.0507 35.7136 27.282L35.7168 27.2773Z" fill="#C9605C" />
                            </svg>
                        </div>
                        <div className="delete-text">
                            <h3>{__("Are you sure?", "new-user-approve")}</h3>
                            <p>{__("The role will be permanently deleted and users with this role will be moved to Subscriber.", "new-user-approve")}</p>
                        </div>
                    </DialogContent>
                    <DialogActions style={{ padding: "20px" }}>
                        <Button
                            className="cancelBtn"
                            onClick={() => { setDeleteModal(false); setRoleToDelete(null); }}
                            color="primary"
                        >
                            {__("Cancel", "new-user-approve")}
                        </Button>
                        <Button className="importBtn nua-btn" onClick={handleDelete} disabled={submitting}>
                            {__("Delete", "new-user-approve")}
                            {submitting ? (
                                <div className="new-user-approve-loading">
                                    <div className="nua-spinner"></div>
                                </div>
                            ) : (
                                ""
                            )}
                        </Button>
                    </DialogActions>
                </Dialog>

                {/* ── Modal: Add Role ── */}
                <Dialog
                    open={showAdd}
                    onClose={() => { setShowAdd(false); setNewName(""); setNewSlug(""); }}
                    maxWidth="sm"
                    fullWidth
                    className="openNuaModal NuaModal openNuaTabsModal"
                >
                    <DialogTitle>{__("Add New Role", "new-user-approve")}</DialogTitle>
                    <IconButton
                        aria-label="close"
                        className="nua-modal-close"
                        onClick={() => { setShowAdd(false); setNewName(""); setNewSlug(""); }}
                        sx={(theme) => ({
                            position: "absolute",
                            right: 8,
                            top: 12,
                            color: theme.palette.grey[500],
                        })}
                    >
                        <CloseIcon />
                    </IconButton>
                    <DialogContent dividers>
                        <div className="nua-re-form-group">
                            <label>{__("Display Name", "new-user-approve")}</label>
                            <input
                                type="text"
                                className="auto-code-field"
                                value={newName}
                                placeholder={__("e.g. Shop Manager", "new-user-approve")}
                                onChange={(e) => { setNewName(e.target.value); setNewSlug(autoSlug(e.target.value)); }}
                            />
                        </div>
                        <div className="nua-re-form-group">
                            <label>{__("Role Slug", "new-user-approve")}</label>
                            <input
                                type="text"
                                className="auto-code-field"
                                value={newSlug}
                                placeholder={__("e.g. shop_manager", "new-user-approve")}
                                onChange={(e) => setNewSlug(autoSlug(e.target.value))}
                            />
                        </div>
                    </DialogContent>
                    <DialogActions style={{ padding: "20px" }}>
                        <Button
                            className="cancelBtn"
                            onClick={() => { setShowAdd(false); setNewName(""); setNewSlug(""); }}
                            color="primary"
                        >
                            {__("Cancel", "new-user-approve")}
                        </Button>
                        <button className="importBtn nua-btn" onClick={handleAdd} disabled={submitting}>
                            {__("Create Role", "new-user-approve")}
                            {submitting ? (
                                <div className="new-user-approve-loading">
                                    <div className="nua-spinner"></div>
                                </div>
                            ) : (
                                ""
                            )}
                        </button>
                    </DialogActions>
                </Dialog>

                {/* ── Modal: Edit Role ── */}
                <Dialog
                    open={Boolean(editRole)}
                    onClose={() => setEditRole(null)}
                    maxWidth="sm"
                    fullWidth
                    className="openNuaModal NuaModal openNuaTabsModal"
                >
                    <DialogTitle>{__("Edit Role", "new-user-approve")}</DialogTitle>
                    <IconButton
                        aria-label="close"
                        className="nua-modal-close"
                        onClick={() => setEditRole(null)}
                        sx={(theme) => ({
                            position: "absolute",
                            right: 8,
                            top: 12,
                            color: theme.palette.grey[500],
                        })}
                    >
                        <CloseIcon />
                    </IconButton>
                    <DialogContent dividers>
                        <div className="nua-re-form-group">
                            <label>{__("Display Name", "new-user-approve")}</label>
                            <input
                                type="text"
                                className="auto-code-field"
                                value={editName}
                                placeholder={__("e.g. Shop Manager", "new-user-approve")}
                                onChange={(e) => {
                                    setEditName(e.target.value);
                                    if (editRole && editRole.is_custom) setEditSlug(autoSlug(e.target.value));
                                }}
                            />
                        </div>
                        <div className="nua-re-form-group">
                            <label>
                                {__("Role Slug", "new-user-approve")}
                                {editRole && !editRole.is_custom && (
                                    <span className="nua-re-optional"> ({__("built-in, cannot change", "new-user-approve")})</span>
                                )}
                            </label>
                            <input
                                type="text"
                                className="auto-code-field"
                                value={editSlug}
                                readOnly={editRole && !editRole.is_custom}
                                style={editRole && !editRole.is_custom ? { background: "#f5f5f5", color: "#999" } : {}}
                                placeholder={__("e.g. shop_manager", "new-user-approve")}
                                onChange={(e) => { if (editRole && editRole.is_custom) setEditSlug(autoSlug(e.target.value)); }}
                            />
                        </div>
                    </DialogContent>
                    <DialogActions style={{ padding: "20px" }}>
                        <Button
                            className="cancelBtn"
                            onClick={() => setEditRole(null)}
                            color="primary"
                        >
                            {__("Cancel", "new-user-approve")}
                        </Button>
                        <button className="importBtn nua-btn" onClick={handleEdit} disabled={submitting}>
                            {__("Update Role", "new-user-approve")}
                            {submitting ? (
                                <div className="new-user-approve-loading">
                                    <div className="nua-spinner"></div>
                                </div>
                            ) : (
                                ""
                            )}
                        </button>
                    </DialogActions>
                </Dialog>
            </div>
        </div>
    );
};

export default RolesList;
