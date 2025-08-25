import React, {useEffect, useState} from 'react';
import axios from 'axios';
import { sprintf, __ } from '@wordpress/i18n';
import Pagination from '@mui/material/Pagination';
import Stack from '@mui/material/Stack';
import "react-toastify/dist/ReactToastify.css";
import { toast, ToastContainer } from "react-toastify";
import Skeleton from '@mui/material/Skeleton';
import { 
    Table,
    TableBody,
    TableCell, 
    TableContainer, 
    TableHead, 
    TableRow, 
    Paper,  
    IconButton, 
    Box} from '@mui/material';

// custom component
import { action_status } from '../../../functions';
import { update_user_status } from '../../../functions';
import { site_url } from '../../../functions';

const Pending_Users = () => {
    const [usersData, setUserData] = useState([]);
    const [loading, setLaoding] = useState(true);
    const [loadingUserId, setLoadingUserId] = useState(null); 
    const [error, setError] = useState(null);
    const [anchorEl, setAnchorEl] = useState(null);
    const [user_id, setUserID]   = useState(null);
    const [page, setPage] = useState(1);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [totalUsers, setTotalUsers] = useState(0);
    const [search, setSearch] = useState("");
    const [searchLoading, setSearchLoading] = useState(false);

    const fetchPendingUsers = async () => {
        try{
            setLaoding(true);
            const response = await axios.get(`${NUARestAPI.get_pending_users+NUARestAPI.permalink_delimeter}page=${page}&limit=${rowsPerPage}&search=${search}`,{ 
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
              });
            const data = response.data;
            setUserData(data.users);
            setTotalUsers(data.totals);
        }
        catch (error) {
            setError(error);
        }
        finally {
            setLaoding(false);
            setSearchLoading(false);
        }
    }

    useEffect(() =>{
        fetchPendingUsers();

    }, [search, page, rowsPerPage]);

    const pageCount = Math.ceil(totalUsers / rowsPerPage);

    const handleMenuClose = () => {
        setAnchorEl(null);
        setUserID(null);
    }

    const handleMenuAction = async (event, value) => {
        const userId  = value;
        const user_status = event.currentTarget.getAttribute('data-value');
        const userdata = {
          userID : userId, status_label : user_status
        }
        try {
            setLoadingUserId(userId);
            const response =  await update_user_status('update-user', userdata );
            if(response.message = 'Success') {
                  toast.success(
                __('Status has been changed successfully', "new-user-approve"),
                {
                position: "bottom-right",
                autoClose: 2000,
                hideProgressBar: false,
                closeOnClick: true,
                pauseOnHover: true,
                draggable: true,
                }
                );
                fetchPendingUsers();
                handleMenuClose();
            }
            else if(response.message == 'Failed') {
                 toast.error(
                __("Failed to update user status", "new-user-approve"),
                {
                    position: "bottom-right",
                    autoClose: 2000,
                    hideProgressBar: false,
                    closeOnClick: true,
                    pauseOnHover: true,
                    draggable: true,
                }
                );
                setLoadingUserId(false);
                handleMenuClose();
                alert('Failed to update user status');

                
            }
        }
        finally {
            setLoadingUserId(null);
        }

    }

    let iconApprove = (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="0.5" y="0.5" width="23" height="23" rx="1.5" fill="#FAFAFA"/>
        <rect x="0.5" y="0.5" width="23" height="23" rx="1.5" stroke="#E6EBEF"/>
        <path fillRule="evenodd" clipRule="evenodd" d="M11.1 11.1C13.3368 11.1 15.15 9.28675 15.15 7.05C15.15 4.81325 13.3368 3 11.1 3C8.86325 3 7.05 4.81325 7.05 7.05C7.05 9.28675 8.86325 11.1 11.1 11.1ZM11.1 11.1C6.627 11.1 3 14.727 3 19.2H11.6155C11.1724 18.3999 10.92 17.4795 10.92 16.5C10.92 14.3764 12.1062 12.53 13.8521 11.587C12.9942 11.2738 12.0679 11.1 11.1 11.1ZM16.5 21C18.9853 21 21 18.9853 21 16.5C21 14.0147 18.9853 12 16.5 12C14.0147 12 12 14.0147 12 16.5C12 18.9853 14.0147 21 16.5 21ZM19.0418 15.2169L16.3418 17.9169L15.96 18.2987L15.5781 17.9169L13.9581 16.2969L14.7218 15.5332L15.96 16.7713L18.2781 14.4532L19.0418 15.2169Z" fill="#618E5F"/>
        </svg>
        
      );
      
      let iconDeny = (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="0.5" y="0.5" width="23" height="23" rx="1.5" stroke="#E6EBEF"/>
        <path fillRule="evenodd" clipRule="evenodd" d="M11.1 11.1C13.3368 11.1 15.15 9.28675 15.15 7.05C15.15 4.81325 13.3368 3 11.1 3C8.86325 3 7.05 4.81325 7.05 7.05C7.05 9.28675 8.86325 11.1 11.1 11.1ZM11.1 11.1C6.627 11.1 3 14.727 3 19.2H11.6155C11.1724 18.3999 10.92 17.4795 10.92 16.5C10.92 14.3764 12.1062 12.53 13.8521 11.587C12.9942 11.2738 12.0679 11.1 11.1 11.1ZM16.5 21C18.9853 21 21 18.9853 21 16.5C21 14.0147 18.9853 12 16.5 12C14.0147 12 12 14.0147 12 16.5C12 18.9853 14.0147 21 16.5 21Z" fill="#C9605C"/>
        <path className="nua-path-class" d="M19 14.9927L17.4921 16.4994L19 18.0073L18.0073 19L16.4994 17.4921L14.9927 19L14 18.0073L15.5067 16.4994L14 14.9927L14.9927 14L16.4994 15.5067L18.0073 14L19 14.9927Z" fill="beige"/>
        </svg>  
    );
    
    let notFound = (
        <svg width="68" height="48" viewBox="0 0 68 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g clipPath="url(#clip0_774_1213)">
        <path d="M30.4245 6.77262H23.8308V0.504771C23.8308 0.225642 23.6037 0 23.3227 0H5.52032C5.23934 0 5.01221 0.225642 5.01221 0.504771V9.85306C5.01221 10.1322 5.23934 10.3578 5.52032 10.3578H28.5166L30.8165 7.59997C31.0908 7.2707 30.8552 6.77262 30.4245 6.77262Z" fill="url(#paint0_linear_774_1213)"/>
        <path d="M36.3433 3.5499L29.4266 11.8435H2.02731C1.15915 11.8435 0.465961 12.5622 0.501293 13.423L1.89776 46.2648C1.93309 47.0772 2.6044 47.7173 3.42209 47.7173H64.0774C64.8934 47.7173 65.5648 47.0788 65.6018 46.2699L67.4979 4.58451C67.5366 3.72206 66.8434 3 65.9736 3H37.516C37.0617 3 36.631 3.20057 36.3416 3.54823L36.3433 3.5499Z" fill="url(#paint1_linear_774_1213)"/>
        </g>
        <defs>
        <linearGradient id="paint0_linear_774_1213" x1="17.9732" y1="0" x2="17.9732" y2="10.3578" gradientUnits="userSpaceOnUse">
        <stop stop-color="#ECF6EE"/>
        <stop offset="1" stop-color="#E1EEE3"/>
        </linearGradient>
        <linearGradient id="paint1_linear_774_1213" x1="33.9997" y1="3" x2="33.9997" y2="47.7173" gradientUnits="userSpaceOnUse">
        <stop stop-color="#ECF6EE"/>
        <stop offset="1" stop-color="#E1EEE3"/>
        </linearGradient>
        <clipPath id="clip0_774_1213">
        <rect width="67" height="48" fill="white" transform="translate(0.5)"/>
        </clipPath>
        </defs>
        </svg>
    );

    return (

        <div className = "pending_users_list">
      
        <div className = "all_users_list_header">
            <h2 className='users_list_title' >  {__('Pending Users', 'new-user-approve') }</h2>
            {/* search field */}
            <Box className="nua-search-field-box" component="form" noValidate autoComplete="off"  sx={ {  width: '30ch', position:'relative' } }>
                <input type="text" className='nua-search-field' placeholder="Search User"  onChange={(e) => { setSearch(e.target.value); setPage(1); setSearchLoading(true); }}/>
                { searchLoading && (
                <div className='new-user-approve-loading nua-search-loading'>
                <div className="nua-spinner"></div></div> )}
            </Box>   
        </div>
        <TableContainer className="pending_users_tbl_container usersTable" component={Paper}>
            <Table sx={{ minWidth: 650 }}>
                <TableHead>
                <TableRow sx= {{ backgroundColor: '#FAFAFA', maxHeight:50, minHeight:50, height:50  }}>
                    <TableCell> {__('User', 'new-user-approve') }</TableCell>
                    <TableCell sx={{paddingLeft:4}}> {__('Email', 'new-user-approve') }</TableCell>
                    <TableCell sx={{textAlign:`${usersData.length > 0 ? "" : "center !important"}`, width:220}}> {__('Registration Date', 'new-user-approve') }</TableCell>
                    <TableCell sx={{paddingLeft:4}}> {__('Status', 'new-user-approve') }</TableCell>
                    <TableCell sx={{textAlign:'left !important'}}> {__('Actions', 'new-user-approve') }</TableCell>
                    {/* <TableCell></TableCell> */}
                </TableRow>
                </TableHead>
                <TableBody>
        {loading ? (
            // Show 5 skeleton rows while loading
            Array.from({ length: 10 }).map((_, index) => (
            <TableRow key={index}>
                <TableCell><Skeleton variant="text" /></TableCell>
                <TableCell><Skeleton variant="text" /></TableCell>
                <TableCell><Skeleton variant="text" width={100} /></TableCell>
                <TableCell><Skeleton variant="rectangular" width={80} height={30} /></TableCell>
                <TableCell><Skeleton variant="circular" width={24} height={24} /></TableCell>
            </TableRow>
            ))
        ) : usersData.length > 0 ? (
            usersData.map((row) => (
                    <TableRow id={row.ID}>
                    <TableCell><a href={`${site_url()}/wp-admin/user-edit.php?user_id=${row.ID}`} style={{textDecoration:'none', color:'#858585'}}>{row.display_name}</a></TableCell>
                    <TableCell>{row.user_email}</TableCell>
                    <TableCell>{row.user_registered}</TableCell>
                    <TableCell>
                        <div className="nua-status-container">
                            <span className={'user-'+row.nua_status}>{row.nua_status.charAt(0).toUpperCase() + row.nua_status.slice(1)}</span> 
                            <span> { user_id === row.ID && loading == true ?  <div className='new-user-approve-loading'><div className="nua-spinner"></div></div> : <span className="loadEmpty" style={{marginLeft:13}}></span> }  </span>
                        </div>
                    </TableCell>
                    
                         <TableCell align="center" className="user-action-btn">
                            {action_status(row.nua_status).map((status) => (
                        <React.Fragment key={status}>
                        <IconButton
                            onClick={(event) => handleMenuAction(event, row.ID)}
                            style={{ cursor:'pointer', paddingLeft:'0' }}
                            data-value={status}
                            className={status}
                            title={status.charAt(0).toUpperCase() + status.slice(1)}
                        >
                            <div className='statusDiv'>
                            {status === 'approve' ? iconApprove : iconDeny}
                            </div>
                        </IconButton>

                        {loadingUserId === row.ID && (
                            <div className="new-user-approve-loading">
                            <div className="nua-spinner"></div>
                            </div>
                        )}
                        </React.Fragment>
                    ))}
                    </TableCell>
                    </TableRow>
                 ))
                ) : (
          
                <TableRow>
                    <TableCell colSpan={5}>
                    <div className="user-list-empty pending-user-empty-list" style={{ textAlign: 'center' }}>
                        <div className="user-found-error">
                        {notFound}
                        <span>{__('No Data Available', 'new-user-approve')}</span>
                        <p className="description">{__('There’s no data available to see!', 'new-user-approve')}</p>
                        </div>
                    </div>
                    </TableCell>
                </TableRow>
         )}
         </TableBody>
            </Table>
        </TableContainer>
        {usersData.length > 0 && (
            <Stack spacing={2} alignItems="center" mt={2} className="nua-table-pagination">
                <Pagination
                count={Math.max(1, pageCount)}
                page={page}
                onChange={(event, value) => setPage(value)}
                variant="outlined"
                shape="rounded"
                className ="nua-nav-pagination"
                />
            </Stack>
        )}
        <ToastContainer />
        </div>
    );

}

export default Pending_Users;