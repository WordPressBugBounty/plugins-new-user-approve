import React, {useState, useEffect} from 'react';
import ReactDOM from 'react-dom';
import { Table, TableBody, TableCell, TableContainer, TableRow, Typography, Paper } from '@mui/material';
import { styled } from '@mui/system';
import { sprintf, __ } from '@wordpress/i18n';

import { get_activity_log } from '../../functions';

import { site_url } from '../../functions';

const Recent_Updates = ({ statusUpdated = false }) => {

    const [user_logs, setUserLogs] = useState([]);
    let [frames, Frames] = useState([]);
    let [countFrames, setCountFrames] = useState(0);

    const fetchActivityLog = async () => {

        const response = await get_activity_log();
        console.log(response);
        setUserLogs(response.data.data || []);
       
    };

    useEffect(() => {
      fetchActivityLog();
     },  [statusUpdated])

    return (
        
      <TableContainer className='recent-update-frame-container' component={Paper} sx={{width:280}}>
      <Table className="frames-table">
        {/* <TableBody>
        {user_logs.length > 0 ? user_logs.map((item, index) => {
            // Get the key and value of the current object
            const [key, log] = Object.entries(item)[0];
            {var frame = key == 'approved' ? 1 : key == 'denied' ? 3 : key=='pending' ? 2 : 0}
            {frames.push(frame) }
            {countFrames =  (parseInt(index) + parseInt(1))}
            return (
              <>
              <TableRow className={`frame-${frame}-table-row activity-log-frame`}>
                <div className='activity-container'>
                    <div className='activity-header'>
                      <span className='activity-title'>  {__('Activity', 'new-user-approve') } </span>
                      <span className='activity-period'> { Object.entries(log).length != 0 ?  log.status_time : '' } </span>
                    </div>
                    <span className={`activity-desc ${key}-desc`}>
                    { Object.entries(log).length != 0 ? (
                      __(<> User registration request {key} for   <a href={`${site_url()}/wp-admin/user-edit.php?user_id=${log.ID}`} style={{textDecoration:'none', color:'#618E5F'}}>{log.display_name}</a> </> , 'new-user-approve') 
                    ) : __('No recent activities', 'new-user-approve') }
                    </span>
                    {index == 2 && (
                      <div className='see-all-activity'>
                        <a href="/wp-admin/admin.php?page=new-user-approve-admin#/action=users/tab=all-users" className='see-all-activity-btn'>{__('See All', 'new-user-approve') }</a>
                      </div>
                    )}
                </div>

              </TableRow>
              {countFrames < 3 && countFrames == user_logs.length ? 
              ['pending','approved','denied'].map((status, index) => {
                {var frame_number = parseInt(index) + parseInt(1)}
               
                return (
                  <> 
                  {!frames.includes(frame_number) && 
                    <TableRow className={`frame-${frame_number}-table-row activity-log-frame`}>
                      <div className='activity-container'>
                          <div className='activity-header'>
                            <span className='activity-title'>  {__('Activity', 'new-user-approve') } </span>
                            <span className='activity-period'> </span>
                          </div>
                          <span className={`activity-desc ${status}-desc`}>
                          {  __('No recent activities', 'new-user-approve') }
                          </span>
                      </div>
                    </TableRow>
                  }
                  </>
                );
              })
            :''}        
            </>
              
              );
            }) : <div>
                   {['pending','approved','denied'].map((status, index) => {
                    return (
                      <TableRow className={`frame-${parseInt(index) + parseInt(1)}-table-row activity-log-frame`}>
                      <div className='activity-container'>
                            <div className='activity-header'>
                              <span className='activity-title'> {__('Activity', 'new-user-approve') } </span>
                              <span className='activity-period'> </span>
                            </div>
                            <span className={`activity-desc ${status}-desc`}>
                             {__('No recent activities', 'new-user-approve') }
                            </span>
                        </div>
                    </TableRow>
                    );
                  }) }
                </div>
          }
        </TableBody> */}
        <TableBody>
  {['approved', 'pending', 'denied'].map((status, index) => {
    const frame = status === 'approved' ? 1 : status === 'pending' ? 2 : 3;
    const logObj = user_logs.find((item) => Object.keys(item)[0] === status);
    const log = logObj ? Object.values(logObj)[0] : null;

    return (
      <TableRow key={status} className={`frame-${frame}-table-row activity-log-frame`}>
        <div className='activity-container'>
          <div className='activity-header'>
            <span className='activity-title'>{__(status , 'new-user-approve')}</span>
            <span className='activity-period'>{log ? log.status_time : ''}</span>
          </div>
          <span className={`activity-desc ${status}-desc`}>
            {log ? (
              __(
                <>
                  User registration request {status} for{' '}
                  <a
                    href={`${site_url()}/wp-admin/user-edit.php?user_id=${log.ID}`}
                    style={{ textDecoration: 'none', color: '#618E5F' }}
                  >
                    {log.display_name}
                  </a>
                </>,
                'new-user-approve'
              )
            ) : (
              __('No recent activities', 'new-user-approve')
            )}
          </span>

          {/* See All on the last frame */}
          {index === 2 && (
            <div className='see-all-activity'>
              <a
                href="/wp-admin/admin.php?page=new-user-approve-admin#/action=users/tab=all-users"
                className='see-all-activity-btn'
              >
                {__('See All', 'new-user-approve')}
              </a>
            </div>
          )}
        </div>
      </TableRow>
    );
  })}
</TableBody>

        
      </Table>
    </TableContainer>
    );

}

export default Recent_Updates;