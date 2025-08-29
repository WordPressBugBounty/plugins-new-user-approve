import React, {useEffect, useState} from 'react';
import axios from 'axios';
import { sprintf, __ } from '@wordpress/i18n';
import { user_role_dummy } from '../../../functions';
import { 
    Table,
    TableBody,
    TableCell, 
    TableContainer, 
    TableHead, 
    TableRow, 
    Paper, 
    Button, 
    IconButton, 
    TextField, 
    Menu, 
    MenuItem, } from '@mui/material';
import MoreVertIcon from '@mui/icons-material/MoreVert';

import { action_status } from '../../../functions';
import { update_user_status } from '../../../functions';
import Update_User_Role_Modal from './update-user-role-modal';
import { site_url } from '../../../functions';
import { border } from '@mui/system';
import zIndex from '@mui/material/styles/zIndex';
const icons = require.context('../../../assets/icons', false, /\.(png|svg|jpe?g|)$/);

const User_Roles = () => {
    // const [usersData, setUserData] = useState([]);
    const [loading, setLaoding]   = useState(true);
    const [error, setError]       = useState(null);
    const [anchorEl, setAnchorEl] = useState(null);
    const [user_id, setUserID]    = useState(null);
    const [open, setOpen]         = useState(false);

    const [reload, setReload] = useState(false);
    const [isPopupVisible, setPopupVisible] = useState(false);
    let usersData = user_role_dummy();
    

    let AddBanner = icons(`./pro-banner-vector-laptop.svg`);
    let edit_icon = icons(`./Edit.svg`);
    const fetchUsers = async () => {
        try{
            setLaoding(true);
            const response = await axios.get(`${NUARestAPI.get_approved_user_roles}`, 
            { 
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
            });
            const data = response.data;
            // setUserData(data);
        }
        catch (error) {
            setError(error);
        }
        finally {
            setLaoding(false);
        }
    }

    useEffect(() =>{
        fetchUsers();

    }, [reload]);

    const handleEditChange = () => {

        setPopupVisible(true);
        setAnchorEl(null);
        setUserID(null);
    }
    

    const handleMenuOpen = ( event, userId ) => {

        setAnchorEl(event.currentTarget);
        setUserID(userId);
       
    }

    const handleMenuClose = () => {
        setAnchorEl(null);
        setUserID(null);
    }

    const handleCloseModal = () => {
        setOpen(false);

    }

    const handleMenuAction =  (event, user_id) =>  {
        setOpen(true);
        setAnchorEl(null);
        setUserID(user_id)        

    }



    return (

        <div className = "user_roles_list" style={{position:"relative"}}>
        <h2 className='users_list_title'> User Roles</h2>
        

        <TableContainer className="user_roles_tbl_container usersTable" component={Paper} style={isPopupVisible ? styles.tableColorChange : {}}>
            <Table sx={{ minWidth: 650 }}>
                <TableHead>
                <TableRow sx= {{ backgroundColor: '#FAFAFA', maxHeight:50, minHeight:50, height:50  }}>
                    <TableCell> {__('Username', 'new-user-approve') }</TableCell>
                    <TableCell> {__('Current Role', 'new-user-approve') }</TableCell>
                    <TableCell sx={{paddingLeft:4}}> {__('Email', 'new-user-approve') }</TableCell>
                    <TableCell> {__('Requested Role', 'new-user-approve') }</TableCell>
                    <TableCell align="left"> {__('Action', 'new-user-approve') }</TableCell>
                    <TableCell></TableCell>
                </TableRow>
                </TableHead>
                { usersData.length > 0 ? (
                <TableBody>

                {usersData.map((row) => (
                    
                    <TableRow id={row.username}>
                    <TableCell><a href={`${site_url()}/wp-admin/user-edit.php?user_id=${row.ID}`} style={{textDecoration:'none', color:'#858585'}}>{row.username}</a></TableCell>
                    <TableCell>{row.current_role}</TableCell>
                    <TableCell>{row.email_address}</TableCell>
                    <TableCell>{row.requested_role}</TableCell>
                    <TableCell align="left">

                        <div style={{display:'flex'}} className="action_edit_btn">
                            <img style={{ cursor:'pointer' }} onClick= {handleEditChange} src={edit_icon} alt="edit" />
                            <p style={{ cursor:'pointer' }} onClick= {handleEditChange}>{__('Edit', 'new-user-approve') }</p>
                        </div>

                    </TableCell>
                    <TableCell></TableCell>
                    </TableRow>
                ))}
                </TableBody>
                
            ) : 
            
            <TableBody> 
                 <TableRow >
                <TableCell></TableCell>
                <TableCell></TableCell>
                <TableCell>
                <div className='user-list-empty'>
                    
                   { loading == true ?  <div className='new-user-approve-loading'>
                            <div className="nua-spinner"></div></div> 
                     :
                     <div className="user-found-error">
                        <img src={not_found} alt="" />
                        <span > {__('No Data Available', 'new-user-approve') }</span>
                        <p className='description'>{__('There’s no data available to see!', 'new-user-approve')}</p>
                    </div>
                    }
                
                </div>
                </TableCell>
                </TableRow>
            </TableBody>
            } 
            </Table>
        </TableContainer>
        
        <Update_User_Role_Modal user_id = {user_id} open={open} handleClose={handleCloseModal} setReload = {setReload}/>

        { isPopupVisible && (
      <div className="nua-parent-popup" style={{ ...styles.popupOverlay, top: "55px" }}>
        
      <div className='nua-pro-small-banner'>

         <div style={styles.popupContent} >
         <div className ="nua-pro-close">
                <span className='nua-pro-close-empty'></span>
                <span style={styles.closeButton} onClick={() => setPopupVisible(false)} >&times;</span>
            </div>
         <img 
                 src={AddBanner} 
                 alt="Popup" 
             />
            <div className='nua-pro-small-banner-content'>
               <h1>{__('Authenticate User Approval Process With New User Approve', 'new-user-approve') }</h1>
               <p> {__('Join over 20,000 users who are making their WordPress sites free from spam registration and fake user signups', 'new-user-approve') }</p>
               <div className="nua-pro-small-banner-btns">
                 <button onClick={ () => { window.location.href = 'https://newuserapprove.com/pricing/'; }}>  {__('Get Pro Now', 'new-user-approve') } </button>
                 <a href="https://newuserapprove.com/" >  {__('Learn More', 'new-user-approve') } </a>

               </div>
             </div>
           </div>
            
             
         </div>
         </div>
         ) }

        </div>
    );

}

const styles = {
    tableColorChange: {
      filter: 'grayscale(100%) brightness(0.5) contrast(1) opacity(0.6)',
      backgroundColor: 'rgba(0, 0, 0, 0.5)'
  },
  
  popupOverlay: {
    position: 'absolute',
    top: 20,
    left: 0,
    width: '100%',
    height: '100%',
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
    pointerEvents: "none", 
  },
  popupContent: {

  display: 'flex',
  flexDirection: 'row-reverse',
  alignItems: 'center',
  height: '225px',
  zIndex: 2, 
  pointerEvents: "auto",
  padding: "20px 0px 20px 10px",

  },
  closeButton: {
  fontSize: '24px',
  cursor: 'pointer',
  marginBottom:'225px'
  },
  
  };


export default User_Roles;