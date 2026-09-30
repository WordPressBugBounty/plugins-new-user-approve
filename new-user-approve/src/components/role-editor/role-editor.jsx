import React, { useState, useEffect, useCallback } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { toast, ToastContainer } from "react-toastify";
import Skeleton from "@mui/material/Skeleton";
import {
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
    re_get_capabilities,
    re_create_role,
    re_delete_role,
    re_update_role_caps,
    re_rename_role,
    re_add_custom_cap,
    re_update_custom_cap,
    re_delete_custom_cap,
} from "../../functions";

// ── Bulk select checkbox (supports indeterminate state) ─────────────────────
const BulkCheckbox = ({ caps, capabilities, onToggle, disabled }) => {
    const ref = React.useRef(null);
    const grantedCount = caps.filter((c) => capabilities?.[c]).length;
    const allChecked = caps.length > 0 && grantedCount === caps.length;
    const someChecked = grantedCount > 0 && grantedCount < caps.length;
    React.useEffect(() => {
        if (ref.current) ref.current.indeterminate = someChecked;
    }, [someChecked]);
    return (
        <input
            type="checkbox"
            ref={ref}
            className="nua_checkbox nua-re-bulk-checkbox"
            checked={allChecked}
            disabled={disabled}
            title={allChecked ? __('Deselect all', 'new-user-approve') : __('Select all', 'new-user-approve')}
            onChange={() => onToggle(caps, !allChecked)}
            onClick={(e) => e.stopPropagation()}
        />
    );
};

// ── Main component ────────────────────────────────────────────────────────────
const RoleEditor = () => {
    const [roles, setRoles] = useState([]);
    const [coreCaps, setCoreCaps] = useState([]);
    const [openSections, setOpenSections] = useState({ core: true, plugin: false, custom: true });
    const toggleSection = (key) => setOpenSections((prev) => ({ ...prev, [key]: !prev[key] }));
    const selectedRoleSlugRef = React.useRef(null);
    const [openPluginSections, setOpenPluginSections] = useState({});
    const togglePluginSection = (key) => setOpenPluginSections((prev) => ({ ...prev, [key]: !prev[key] }));
    const [capSearch, setCapSearch] = useState("");
    const [pluginCaps, setPluginCaps] = useState({ nua: [], grouped: {}, others: [] });
    const [customCaps, setCustomCaps] = useState([]);
    const [selectedRole, setSelectedRole] = useState(null);
    const [saving, setSaving] = useState(false);
    const [loading, setLoading] = useState(true);
    const [humanReadable, setHumanReadable] = useState(() => localStorage.getItem('nua_re_human_readable') === '1');

    const [showAddCap, setShowAddCap] = useState(false);
    const [newCapName, setNewCapName] = useState("");
    const [addingCap, setAddingCap] = useState(false);

    const [deleteModal, setDeleteModal] = useState(false);
    const [capToDelete, setCapToDelete] = useState(null);
    const [deletingCap, setDeletingCap] = useState(false);

    const [editingCap, setEditingCap] = useState(null); // { old_name, new_name }
    const [roleSearch, setRoleSearch] = useState("");

    // ── Load data on mount ──────────────────────────────────────────────────────
    const loadData = useCallback(async ({ silent = false } = {}) => {
        if (!silent) {
            setLoading(true);
        }
        const [rolesRes, capsRes] = await Promise.all([re_get_roles({ limit: 9999 }), re_get_capabilities()]);
        if (rolesRes.data) {
            const rolesArray = Array.isArray(rolesRes.data) ? rolesRes.data : (rolesRes.data.roles || []);
            // The administrator role remains available to the Roles tab and REST API,
            // but must not be editable from the Capabilities tab.
            const editableRoles = rolesArray.filter((role) => role.slug !== "administrator");
            setRoles(editableRoles);
            const currentSlug = selectedRoleSlugRef.current;
            if (currentSlug) {
                const refreshed = editableRoles.find((r) => r.slug === currentSlug);
                if (refreshed) setSelectedRole(refreshed);
                else if (editableRoles.length > 0) {
                    setSelectedRole(editableRoles[0]);
                    selectedRoleSlugRef.current = editableRoles[0].slug;
                }
            } else if (editableRoles.length > 0) {
                setSelectedRole(editableRoles[0]);
                selectedRoleSlugRef.current = editableRoles[0].slug;
            }
        }
        if (capsRes.data) {
            setCoreCaps(capsRes.data.core_caps || []);
            setPluginCaps(capsRes.data.plugin_caps || { nua: [], grouped: {}, others: [] });
            setCustomCaps(capsRes.data.custom_caps || []);
        }
        if (!silent) {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        loadData();
    }, [loadData]);

    // ── Capability toggle ───────────────────────────────────────────────────────
    const handleCapToggle = (cap, granted) => {
        setSelectedRole((prev) => ({
            ...prev,
            capabilities: { ...prev.capabilities, [cap]: granted },
        }));
    };

    // ── Save caps ───────────────────────────────────────────────────────────────
    const handleSaveCaps = async () => {
        setSaving(true);
        const res = await re_update_role_caps({
            role_slug: selectedRole.slug,
            capabilities: selectedRole.capabilities,
        });
        setSaving(false);
        if (res.error) {
            toast.error(res.error, { position: 'bottom-right' });
        } else {
            toast.success(__("Capabilities saved.", "new-user-approve"), { position: 'bottom-right' });
            await loadData();
        }
    };


    // ── Add custom cap ──────────────────────────────────────────────────────────
    const handleAddCap = async () => {
        if (!newCapName.trim() || addingCap) return;
        if (/^\d+$/.test(autoSlug(newCapName.trim()))) {
            toast.error(__("Capability name must contain at least one letter.", "new-user-approve"), { position: 'bottom-right' });
            return;
        }
        try {
            setAddingCap(true);
            const res = await re_add_custom_cap({ cap_name: autoSlug(newCapName.trim()) });
            if (res.error) {
                toast.error(typeof res.error === "string" ? res.error : __("Failed to add capability.", "new-user-approve"), { position: 'bottom-right' });
            } else {
                toast.success(__("Capability added.", "new-user-approve"), { position: 'bottom-right' });
                await loadData({ silent: true });
                setShowAddCap(false);
                setNewCapName("");
            }
        } finally {
            setAddingCap(false);
        }
    };

    // ── Edit custom cap ─────────────────────────────────────────────────────────
    const handleEditCap = async () => {
        if (!editingCap?.new_name?.trim()) return;
        if (/^\d+$/.test(autoSlug(editingCap.new_name.trim()))) {
            toast.error(__("Capability name must contain at least one letter.", "new-user-approve"), { position: 'bottom-right' });
            return;
        }
        const res = await re_update_custom_cap({ old_name: editingCap.old_name, new_name: autoSlug(editingCap.new_name.trim()) });
        if (res.error) {
            toast.error(typeof res.error === "string" ? res.error : __("Failed to update capability.", "new-user-approve"), { position: 'bottom-right' });
        } else {
            toast.success(__("Capability updated.", "new-user-approve"), { position: 'bottom-right' });
            setEditingCap(null);
            await loadData();
        }
    };

    // ── Delete custom cap ───────────────────────────────────────────────────────
    const openDeleteCapModal = (cap) => {
        setCapToDelete(cap);
        setDeleteModal(true);
    };

    const closeDeleteCapModal = () => {
        if (deletingCap) return;
        setDeleteModal(false);
        setCapToDelete(null);
    };

    const handleDeleteCap = async () => {
        if (!capToDelete) return;
        try {
            setDeletingCap(true);
            const res = await re_delete_custom_cap({ cap_name: capToDelete });
            if (res.error) {
                toast.error(typeof res.error === "string" ? res.error : __("Failed to delete capability.", "new-user-approve"), { position: 'bottom-right' });
            } else {
                toast.success(__("Capability deleted.", "new-user-approve"), { position: 'bottom-right' });
                await loadData({ silent: true });
                setDeleteModal(false);
                setCapToDelete(null);
            }
        } finally {
            setDeletingCap(false);
        }
    };

    // ── Helpers ─────────────────────────────────────────────────────────────────
    const isGranted = (cap) => !!selectedRole?.capabilities?.[cap];

    const matchesSearch = (cap) => !capSearch.trim() || String(cap).toLowerCase().includes(capSearch.toLowerCase().trim());

    const handleBulkToggle = (caps, grant) => {
        setSelectedRole((prev) => {
            const updated = { ...prev.capabilities };
            caps.forEach((cap) => { updated[cap] = grant; });
            return { ...prev, capabilities: updated };
        });
    };

    const isAdminRole = selectedRole?.slug === 'administrator';

    const sectionHasMatch = (caps) => capSearch.trim() !== "" && caps.some(matchesSearch);

    const autoSlug = (name) => name.toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");

    const formatCap = (cap) => humanReadable
        ? String(cap).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
        : String(cap);

    // ── Render ──────────────────────────────────────────────────────────────────
    if (loading) {
        return (
            <div className="nua-re-layout">
                {/* Sidebar skeleton */}
                <div className="nua-re-sidebar">
                    <Skeleton variant="rectangular" height={36} style={{ borderRadius: 6, marginBottom: 12 }} />
                    <Skeleton variant="rectangular" height={36} style={{ borderRadius: 6, marginBottom: 8 }} />
                    <Skeleton variant="text" width="60%" height={20} />
                    <Skeleton variant="text" width="80%" height={20} />
                    <Skeleton variant="text" width="70%" height={20} />
                    <Skeleton variant="text" width="50%" height={20} />
                </div>
                {/* Main area skeleton */}
                <div className="nua-re-main">
                    <div className="nua-re-main-header">
                        <div>
                            <Skeleton variant="text" width={160} height={28} />
                            <Skeleton variant="text" width={100} height={18} />
                        </div>
                        <Skeleton variant="rectangular" width={120} height={36} style={{ borderRadius: 6 }} />
                    </div>
                    {[1, 2, 3].map((s) => (
                        <div key={s} className="nua-re-cap-section" style={{ marginBottom: 8 }}>
                            <Skeleton variant="rectangular" height={44} style={{ borderRadius: '8px 8px 0 0' }} />
                            <div style={{ padding: 16, display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 8 }}>
                                {Array.from({ length: 8 }).map((_, i) => (
                                    <Skeleton key={i} variant="text" height={32} style={{ borderRadius: 6 }} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div>
            <div className="nua-re-layout">
                {/* ── SIDEBAR: Role selector ── */}
                <div className="nua-re-sidebar">
                    <div className="nua-re-sidebar-header">
                        {/* <button className="nua-btn save-changes nua-re-btn-block" style={{ width: "100%" }} onClick={() => setShowAddRole(true)}>
                            + {__("Add New Role", "new-user-approve")}
                        </button> */}
                        <button className="nua-btn nua-re-btn-block" style={{ width: "100%" }} onClick={() => setShowAddCap(true)}>
                            + {__("Add Capability", "new-user-approve")}
                        </button>
                    </div>
                    <label className="nua-re-human-readable-toggle">
                        <input
                            type="checkbox"
                            checked={humanReadable}
                            onChange={(e) => {
                                setHumanReadable(e.target.checked);
                                localStorage.setItem('nua_re_human_readable', e.target.checked ? '1' : '0');
                            }}
                        />
                        {__("Human Readable Labels", "new-user-approve")}
                    </label>

                    {/* ── Role search ── */}
                    <div className="nua-re-role-search">
                        <input
                            type="text"
                            className="nua-re-role-search-input"
                            placeholder={__('Search roles…', 'new-user-approve')}
                            value={roleSearch}
                            onChange={(e) => setRoleSearch(e.target.value)}
                        />
                        {roleSearch && (
                            <button className="nua-re-search-clear" onClick={() => setRoleSearch('')} title={__('Clear', 'new-user-approve')}>
                                <svg width="12" height="12" viewBox="0 0 14 14" fill="none">
                                    <path d="M1 1l12 12M13 1L1 13" stroke="#888" strokeWidth="1.8" strokeLinecap="round" />
                                </svg>
                            </button>
                        )}
                    </div>

                    {/* ── Roles list ── */}
                    <div className="nua-re-roles-list">
                        {roles.filter((r) =>
                            !roleSearch.trim() ||
                            r.display_name.toLowerCase().includes(roleSearch.toLowerCase().trim()) ||
                            r.slug.toLowerCase().includes(roleSearch.toLowerCase().trim())
                        ).map((role) => (
                            <button
                                key={role.slug}
                                className={`nua-re-role-item${selectedRole?.slug === role.slug ? ' active' : ''}`}
                                onClick={() => { setSelectedRole(role); selectedRoleSlugRef.current = role.slug; }}
                            >
                                <span className="nua-re-role-item-name">{role.display_name}</span>
                                <span className="nua-re-role-item-slug">{role.slug}</span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* ── MAIN: Capabilities ── */}
                <div className="nua-re-main">
                    {!selectedRole ? (
                        <div className="nua-re-empty">{__("Select a role to manage its capabilities.", "new-user-approve")}</div>
                    ) : (
                        <>


                            {/* Search bar + Save row */}
                            <div className="nua-re-toolbar">
                                <div className="nua-re-search-bar nua-re-toolbar-search" style={{ flex: 1 }}>
                                    <input
                                        type="text"
                                        className="nua-re-search-input"
                                        placeholder={__('Search Capabilities', 'new-user-approve')}
                                        value={capSearch}
                                        onChange={(e) => setCapSearch(e.target.value)}
                                    />
                                    {capSearch ? (
                                        <button className="nua-re-search-clear" onClick={() => setCapSearch("")}
                                            title={__('Clear', 'new-user-approve')}>
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                <path d="M1 1l12 12M13 1L1 13" stroke="#888" strokeWidth="1.8" strokeLinecap="round" />
                                            </svg>
                                        </button>
                                    ) : (
                                        <span className="nua-re-search-icon">
                                            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                                                <circle cx="6.5" cy="6.5" r="5" stroke="#888" strokeWidth="1.6" />
                                                <path d="M10.5 10.5L14 14" stroke="#888" strokeWidth="1.6" strokeLinecap="round" />
                                            </svg>
                                        </span>
                                    )}
                                </div>
                                <button className="nua-btn save-changes nua-re-toolbar-save" onClick={handleSaveCaps} disabled={saving || isAdminRole}>
                                    {saving ? __("Saving…", "new-user-approve") : __("Save Changes", "new-user-approve")}
                                </button>
                            </div>

                            {/* Core capabilities */}
                            <div className="nua-re-cap-section">
                                <div className="nua-re-accordion-header" onClick={() => toggleSection('core')}>
                                    <div className="nua-re-accordion-header-actions" onClick={(e) => e.stopPropagation()}>
                                        <BulkCheckbox caps={coreCaps.filter(matchesSearch)} capabilities={selectedRole?.capabilities} onToggle={handleBulkToggle} disabled={isAdminRole} />
                                    </div>
                                    <span>{__("Core Capabilities", "new-user-approve")}</span>
                                    <span className={`nua-re-accordion-icon ${openSections.core ? 'open' : ''}`}><svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" fill="#cccccc"><path d="M4.516 7.548c0.436-0.446 1.043-0.481 1.576 0l3.908 3.747 3.908-3.747c0.533-0.481 1.141-0.446 1.574 0 0.436 0.445 0.408 1.197 0 1.615-0.406 0.418-4.695 4.502-4.695 4.502-0.217 0.223-0.502 0.335-0.787 0.335s-0.57-0.112-0.789-0.335c0 0-4.287-4.084-4.695-4.502s-0.436-1.17 0-1.615z"></path></svg></span>
                                </div>
                                {(openSections.core || sectionHasMatch(coreCaps)) && (
                                    <div className="nua-re-cap-grid nua-re-accordion-body">
                                        {coreCaps.filter(matchesSearch).map((cap) => (
                                            <label key={cap} className={`nua-re-cap-item${isAdminRole ? ' nua-cap-disabled' : ''}`}>
                                                <input
                                                    type="checkbox"
                                                    className={`nua_checkbox`}
                                                    checked={isGranted(cap)}
                                                    disabled={isAdminRole}
                                                    onChange={(e) => handleCapToggle(cap, e.target.checked)}
                                                />
                                                <span className="nua-re-cap-label">{formatCap(cap)}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {(pluginCaps.nua.length > 0 || Object.keys(pluginCaps.grouped || {}).length > 0 || pluginCaps.others.length > 0) && (
                                <div className="nua-re-cap-section">
                                    <div className="nua-re-accordion-header" onClick={() => toggleSection('plugin')}>
                                        <div className="nua-re-accordion-header-actions" onClick={(e) => e.stopPropagation()}>
                                            <BulkCheckbox caps={[...pluginCaps.nua, ...Object.values(pluginCaps.grouped || {}).flat(), ...pluginCaps.others].filter(matchesSearch)} capabilities={selectedRole?.capabilities} onToggle={handleBulkToggle} disabled={isAdminRole} />
                                        </div>
                                        <span>{__("Plugin Capabilities", "new-user-approve")}</span>
                                        <span className={`nua-re-accordion-icon ${openSections.plugin ? 'open' : ''}`}><svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" fill="#cccccc"><path d="M4.516 7.548c0.436-0.446 1.043-0.481 1.576 0l3.908 3.747 3.908-3.747c0.533-0.481 1.141-0.446 1.574 0 0.436 0.445 0.408 1.197 0 1.615-0.406 0.418-4.695 4.502-4.695 4.502-0.217 0.223-0.502 0.335-0.787 0.335s-0.57-0.112-0.789-0.335c0 0-4.287-4.084-4.695-4.502s-0.436-1.17 0-1.615z"></path></svg></span>
                                    </div>

                                    {(openSections.plugin || sectionHasMatch([...pluginCaps.nua, ...Object.values(pluginCaps.grouped || {}).flat(), ...pluginCaps.others])) && (
                                        <div className="nua-re-accordion-body">
                                            {/* New User Approve caps */}
                                            {pluginCaps.nua.filter(matchesSearch).length > 0 && (() => {
                                                const key = '__nua__';
                                                const isOpen = openPluginSections[key] !== false;
                                                return (
                                                    <div className="nua-re-cap-section nua-re-plugin-section">
                                                        <div className="nua-re-accordion-header nua-re-plugin-accordion-header" onClick={() => togglePluginSection(key)}>
                                                            <div className="nua-re-accordion-header-actions" onClick={(e) => e.stopPropagation()}>
                                                                <BulkCheckbox caps={pluginCaps.nua.filter(matchesSearch)} capabilities={selectedRole?.capabilities} onToggle={handleBulkToggle} disabled={isAdminRole} />
                                                            </div>
                                                            <span>{__('New User Approve', 'new-user-approve')}</span>
                                                            <span className={`nua-re-accordion-icon ${isOpen ? 'open' : ''}`}><svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" fill="#cccccc"><path d="M4.516 7.548c0.436-0.446 1.043-0.481 1.576 0l3.908 3.747 3.908-3.747c0.533-0.481 1.141-0.446 1.574 0 0.436 0.445 0.408 1.197 0 1.615-0.406 0.418-4.695 4.502-4.695 4.502-0.217 0.223-0.502 0.335-0.787 0.335s-0.57-0.112-0.789-0.335c0 0-4.287-4.084-4.695-4.502s-0.436-1.17 0-1.615z"></path></svg></span>
                                                        </div>
                                                        {isOpen && (
                                                            <div className="nua-re-cap-grid nua-re-accordion-body">
                                                                {pluginCaps.nua.filter(matchesSearch).map((cap) => (
                                                                    <label key={cap} className={`nua-re-cap-item${isAdminRole ? ' nua-cap-disabled' : ''}`}>
                                                                        <input type="checkbox" className="nua_checkbox" checked={isGranted(cap)} disabled={isAdminRole} onChange={(e) => handleCapToggle(cap, e.target.checked)} />
                                                                        <span className="nua-re-cap-label">{formatCap(cap)}</span>
                                                                    </label>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })()}

                                            {/* Per-plugin grouped caps */}
                                            {Object.entries(pluginCaps.grouped || {}).map(([pluginName, caps]) => {
                                                const visible = caps.filter(matchesSearch);
                                                if (visible.length === 0) return null;
                                                const key = pluginName;
                                                const isOpen = openPluginSections[key] !== false;
                                                return (
                                                    <div key={pluginName} className="nua-re-cap-section nua-re-plugin-section">
                                                        <div className="nua-re-accordion-header nua-re-plugin-accordion-header" onClick={() => togglePluginSection(key)}>
                                                            <div className="nua-re-accordion-header-actions" onClick={(e) => e.stopPropagation()}>
                                                                <BulkCheckbox caps={visible} capabilities={selectedRole?.capabilities} onToggle={handleBulkToggle} disabled={isAdminRole} />
                                                            </div>
                                                            <span>{pluginName}</span>
                                                            <span className={`nua-re-accordion-icon ${isOpen ? 'open' : ''}`}><svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" fill="#cccccc"><path d="M4.516 7.548c0.436-0.446 1.043-0.481 1.576 0l3.908 3.747 3.908-3.747c0.533-0.481 1.141-0.446 1.574 0 0.436 0.445 0.408 1.197 0 1.615-0.406 0.418-4.695 4.502-4.695 4.502-0.217 0.223-0.502 0.335-0.787 0.335s-0.57-0.112-0.789-0.335c0 0-4.287-4.084-4.695-4.502s-0.436-1.17 0-1.615z"></path></svg></span>
                                                        </div>
                                                        {isOpen && (
                                                            <div className="nua-re-cap-grid nua-re-accordion-body">
                                                                {visible.map((cap) => (
                                                                    <label key={cap} className={`nua-re-cap-item${isAdminRole ? ' nua-cap-disabled' : ''}`}>
                                                                        <input type="checkbox" className="nua_checkbox" checked={isGranted(cap)} disabled={isAdminRole} onChange={(e) => handleCapToggle(cap, e.target.checked)} />
                                                                        <span className="nua-re-cap-label">{formatCap(cap)}</span>
                                                                    </label>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}

                                            {/* Ungrouped / unrecognised caps */}
                                            {pluginCaps.others.filter(matchesSearch).length > 0 && (() => {
                                                const key = '__others__';
                                                const isOpen = openPluginSections[key] !== false;
                                                return (
                                                    <div className="nua-re-cap-section nua-re-plugin-section">
                                                        <div className="nua-re-accordion-header nua-re-plugin-accordion-header" onClick={() => togglePluginSection(key)}>
                                                            <div className="nua-re-accordion-header-actions" onClick={(e) => e.stopPropagation()}>
                                                                <BulkCheckbox caps={pluginCaps.others.filter(matchesSearch)} capabilities={selectedRole?.capabilities} onToggle={handleBulkToggle} disabled={isAdminRole} />
                                                            </div>
                                                            <span>{__('Other', 'new-user-approve')}</span>
                                                            <span className={`nua-re-accordion-icon ${isOpen ? 'open' : ''}`}><svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" fill="#cccccc"><path d="M4.516 7.548c0.436-0.446 1.043-0.481 1.576 0l3.908 3.747 3.908-3.747c0.533-0.481 1.141-0.446 1.574 0 0.436 0.445 0.408 1.197 0 1.615-0.406 0.418-4.695 4.502-4.695 4.502-0.217 0.223-0.502 0.335-0.787 0.335s-0.57-0.112-0.789-0.335c0 0-4.287-4.084-4.695-4.502s-0.436-1.17 0-1.615z"></path></svg></span>
                                                        </div>
                                                        {isOpen && (
                                                            <div className="nua-re-cap-grid nua-re-accordion-body">
                                                                {pluginCaps.others.filter(matchesSearch).map((cap) => (
                                                                    <label key={cap} className={`nua-re-cap-item${isAdminRole ? ' nua-cap-disabled' : ''}`}>
                                                                        <input type="checkbox" className="nua_checkbox" checked={isGranted(cap)} disabled={isAdminRole} onChange={(e) => handleCapToggle(cap, e.target.checked)} />
                                                                        <span className="nua-re-cap-label">{formatCap(cap)}</span>
                                                                    </label>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })()}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Custom capabilities */}
                            <div className="nua-re-cap-section">
                                <div className="nua-re-accordion-header" onClick={() => toggleSection('custom')}>
                                    <div className="nua-re-accordion-header-actions" onClick={(e) => e.stopPropagation()}>
                                        <BulkCheckbox caps={customCaps.filter(matchesSearch)} capabilities={selectedRole?.capabilities} onToggle={handleBulkToggle} disabled={isAdminRole} />
                                    </div>
                                    <span>{__("Custom Capabilities", "new-user-approve")}</span>
                                    <span className={`nua-re-accordion-icon ${openSections.custom ? 'open' : ''}`}><svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" fill="#cccccc"><path d="M4.516 7.548c0.436-0.446 1.043-0.481 1.576 0l3.908 3.747 3.908-3.747c0.533-0.481 1.141-0.446 1.574 0 0.436 0.445 0.408 1.197 0 1.615-0.406 0.418-4.695 4.502-4.695 4.502-0.217 0.223-0.502 0.335-0.787 0.335s-0.57-0.112-0.789-0.335c0 0-4.287-4.084-4.695-4.502s-0.436-1.17 0-1.615z"></path></svg></span>
                                </div>

                                {(openSections.custom || sectionHasMatch(customCaps)) && (
                                    customCaps.filter(matchesSearch).length === 0 ? (
                                        <p className="nua-re-empty-caps nua-re-accordion-body">{__(capSearch.trim() ? "No matching capabilities." : "No custom capabilities yet.", "new-user-approve")}</p>
                                    ) : (
                                        <div className="nua-re-cap-grid nua-re-accordion-body">
                                            {customCaps.filter(matchesSearch).map((cap) => (
                                                <div key={cap} className={`nua-re-cap-item nua-re-cap-custom${isAdminRole ? ' nua-cap-disabled' : ''}`}>
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            className="nua_checkbox"
                                                            checked={isGranted(cap)}
                                                            disabled={isAdminRole}
                                                            onChange={(e) => handleCapToggle(cap, e.target.checked)}
                                                        />
                                                        <span className="nua-re-cap-label">{formatCap(cap)}</span>
                                                    </label>
                                                    <div className="nua-re-cap-actions">
                                                        <button
                                                            className="nua-re-btn-icon"
                                                            title={__("Edit", "new-user-approve")}
                                                            onClick={() => setEditingCap({ old_name: cap, new_name: cap })}
                                                        >
                                                            <span className="actionsIcon">
                                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M9.99999 4.00002L12 6.00002M8.66666 13.3334H14M3.33332 10.6667L2.66666 13.3334L5.33332 12.6667L13.0573 4.94269C13.3073 4.69265 13.4477 4.35357 13.4477 4.00002C13.4477 3.64647 13.3073 3.30739 13.0573 3.05736L12.9427 2.94269C12.6926 2.69273 12.3535 2.55231 12 2.55231C11.6464 2.55231 11.3074 2.69273 11.0573 2.94269L3.33332 10.6667Z" stroke="#242424" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                                                </svg>
                                                            </span>
                                                        </button>
                                                        <button
                                                            className="nua-re-btn-icon"
                                                            title={__("Delete", "new-user-approve")}
                                                            onClick={() => openDeleteCapModal(cap)}
                                                        >
                                                            <span className="actionsIcon">
                                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M5.46667 2.75198H5.33334C5.40667 2.75198 5.46667 2.69438 5.46667 2.62398V2.75198ZM5.46667 2.75198H10.5333V2.62398C10.5333 2.69438 10.5933 2.75198 10.6667 2.75198H10.5333V3.90398H11.7333V2.62398C11.7333 2.05918 11.255 1.59998 10.6667 1.59998H5.33334C4.74501 1.59998 4.26667 2.05918 4.26667 2.62398V3.90398H5.46667V2.75198ZM13.8667 3.90398H2.13334C1.83834 3.90398 1.60001 4.13278 1.60001 4.41598V4.92798C1.60001 4.99838 1.66001 5.05598 1.73334 5.05598H2.74001L3.15167 13.424C3.17834 13.9696 3.64834 14.4 4.21667 14.4H11.7833C12.3533 14.4 12.8217 13.9712 12.8483 13.424L13.26 5.05598H14.2667C14.34 5.05598 14.4 4.99838 14.4 4.92798V4.41598C14.4 4.13278 14.1617 3.90398 13.8667 3.90398ZM11.655 13.248H4.34501L3.94167 5.05598H12.0583L11.655 13.248Z" fill="#BD5E5E" />
                                                                </svg>
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )
                                )}
                            </div>
                        </>
                    )}
                </div>

                {/* ── Modal: Add Custom Cap ── */}
                <Dialog
                    open={showAddCap}
                    onClose={() => {
                        if (addingCap) return;
                        setShowAddCap(false);
                        setNewCapName("");
                    }}
                    maxWidth="sm"
                    fullWidth
                    className="openNuaModal NuaModal openNuaTabsModal"
                >
                    <DialogTitle>{__("Add Custom Capability", "new-user-approve")}</DialogTitle>
                    <IconButton
                        aria-label="close"
                        className="nua-modal-close"
                        onClick={() => {
                            if (addingCap) return;
                            setShowAddCap(false);
                            setNewCapName("");
                        }}
                        disabled={addingCap}
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
                            <label>{__("Capability Name", "new-user-approve")}</label>
                            <input
                                type="text"
                                className="auto-code-field"
                                value={newCapName}
                                placeholder={__("e.g. view_reports", "new-user-approve")}
                                onChange={(e) => setNewCapName(e.target.value)}
                                disabled={addingCap}
                            />
                        </div>
                    </DialogContent>
                    <DialogActions style={{ padding: "20px" }}>
                        <Button
                            className="cancelBtn"
                            onClick={() => {
                                if (addingCap) return;
                                setShowAddCap(false);
                                setNewCapName("");
                            }}
                            color="primary"
                            disabled={addingCap}
                        >
                            {__("Cancel", "new-user-approve")}
                        </Button>
                        <button
                            type="button"
                            className="importBtn nua-btn"
                            onClick={handleAddCap}
                            disabled={addingCap}
                        >
                            {__("Add", "new-user-approve")}
                            {addingCap ? (
                                <div className="new-user-approve-loading">
                                    <div className="nua-spinner"></div>
                                </div>
                            ) : (
                                ""
                            )}
                        </button>
                    </DialogActions>
                </Dialog>

                {/* ── Modal: Delete Custom Cap ── */}
                <Dialog
                    open={deleteModal}
                    onClose={closeDeleteCapModal}
                    maxWidth="xs"
                    fullWidth={true}
                    className="openNuaModal delete-inv-modal openNuaTabsModal NuaModal"
                >
                    <DialogTitle className="delete-title"></DialogTitle>
                    <IconButton
                        aria-label="close"
                        className="nua-modal-close"
                        onClick={closeDeleteCapModal}
                        disabled={deletingCap}
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
                            <p>
                                {capToDelete
                                    ? sprintf(
                                        /* translators: %s: capability slug */
                                        __("The capability \"%s\" will be permanently deleted and removed from all roles.", "new-user-approve"),
                                        capToDelete
                                    )
                                    : __("The capability will be permanently deleted and removed from all roles.", "new-user-approve")}
                            </p>
                        </div>
                    </DialogContent>
                    <DialogActions style={{ padding: "20px" }}>
                        <Button
                            className="cancelBtn"
                            onClick={closeDeleteCapModal}
                            color="primary"
                            disabled={deletingCap}
                        >
                            {__("Cancel", "new-user-approve")}
                        </Button>
                        <Button className="importBtn nua-btn" onClick={handleDeleteCap} disabled={deletingCap}>
                            {__("Delete", "new-user-approve")}
                            {deletingCap ? (
                                <div className="new-user-approve-loading">
                                    <div className="nua-spinner"></div>
                                </div>
                            ) : (
                                ""
                            )}
                        </Button>
                    </DialogActions>
                </Dialog>

                {/* ── Modal: Edit Custom Cap ── */}
                <Dialog
                    open={Boolean(editingCap)}
                    onClose={() => setEditingCap(null)}
                    maxWidth="sm"
                    fullWidth
                    className="openNuaModal NuaModal openNuaTabsModal"
                >
                    <DialogTitle>{__("Edit Capability", "new-user-approve")}</DialogTitle>
                    <IconButton
                        aria-label="close"
                        className="nua-modal-close"
                        onClick={() => setEditingCap(null)}
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
                            <input
                                type="text"
                                className="auto-code-field"
                                value={editingCap?.new_name || ""}
                                onChange={(e) => setEditingCap((prev) => ({ ...prev, new_name: e.target.value }))}
                            />
                        </div>
                    </DialogContent>
                    <DialogActions style={{ padding: "20px" }}>
                        <Button
                            className="cancelBtn"
                            onClick={() => setEditingCap(null)}
                            color="primary"
                        >
                            {__("Cancel", "new-user-approve")}
                        </Button>
                        <button type="button" className="importBtn nua-btn" onClick={handleEditCap}>
                            {__("Update", "new-user-approve")}
                        </button>
                    </DialogActions>
                </Dialog>
            </div>
        </div>
    );
};

export default RoleEditor;
