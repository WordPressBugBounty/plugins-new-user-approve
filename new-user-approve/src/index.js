import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import { HashRouter as Router , Route, Routes} from 'react-router-dom'; 
import './style.css';
import NUA_Invitation_Layout from './components/invitation-code/nua-invitation-layout';



const container = document.getElementById('nua_dashboard_layout');
if (container) {
  const root = createRoot(container);
  root.render(<App />);
}

// if( document.getElementById( 'nua-invitation-layout' ) ) {
//     ReactDOM.render(
//         <Router>
//             <Routes>
//                 <Route path='/' element={ <NUA_Invitation_Layout/> } />
//                 <Route path='/action=add-codes/*' element={ <NUA_Invitation_Layout/> } />
//                 <Route path='/action=settings' element={ <NUA_Invitation_Layout/> } />
//                 <Route path='/action=import-codes' element={ <NUA_Invitation_Layout/> } />
//                 <Route path='/action=email' element={ <NUA_Invitation_Layout/> } />
//             </Routes>
//         </Router>,
//         document.getElementById('nua-invitation-layout')
//     );
//     // ReactDOM.render(<NUA_Invitation_Layout/> , document.getElementById( 'nua-invitation-layout' ) );
// }


// if(document.getElementById('nua-settings-layout') ) {
//     ReactDOM.render(
        
//         <Router>
//             <Routes>
//                 <Route path='/' element={ <Settings/> } />
//                 <Route path='/action=registration-settings' element={ <Settings/> } />
//                 <Route path='/action=notification-settings/*' element={ <Settings/> } />
//                 <Route path='/action=help' element={ <Settings/> } />
//             </Routes>
//         </Router>,
//         document.getElementById('nua-settings-layout')
//     );
// }


// const syncInputs = () => {
//     const usageLimitInput = document.querySelector(".usage_limit_input");
//     const usageLeftInput = document.querySelector(".usage_left_input");
//     if (usageLimitInput && usageLeftInput) {
//         usageLeftInput.setAttribute("readonly", true);
//         usageLimitInput.addEventListener("input", (event) => {
//             usageLeftInput.value = event.target.value;
//         });
        
//         const observer = new MutationObserver(() => {
//             if (usageLeftInput.value !== usageLimitInput.value) {
//                 usageLeftInput.value = usageLimitInput.value; // Reset if changed manually
//             }
//         });

//         observer.observe(usageLeftInput, { attributes: true, childList: true, subtree: true });
//       }

// };

//  syncInputs();
//  document.addEventListener("DOMContentLoaded", syncInputs);


// if( document.getElementById('nua_dashboard_layout') ) {
//     ReactDOM.render(<App comp = {'nua-dashboard'}/>,  document.getElementById( 'nua_dashboard_layout' ) );
// }

// if( document.getElementById( 'nua-invitation-layout' ) ) {
//     ReactDOM.render(
//             <App comp = {'nua-invitation'} /> ,
//         document.getElementById('nua-invitation-layout')
//     );
//     // ReactDOM.render(<NUA_Invitation_Layout/> , document.getElementById( 'nua-invitation-layout' ) );
// }
 