/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
/*!**************************!*\
  !*** ./src/functions.js ***!
  \**************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   action_status: () => (/* binding */ action_status),
/* harmony export */   delete_invCode: () => (/* binding */ delete_invCode),
/* harmony export */   format_selected_values: () => (/* binding */ format_selected_values),
/* harmony export */   generateAPI: () => (/* binding */ generateAPI),
/* harmony export */   get_activity_log: () => (/* binding */ get_activity_log),
/* harmony export */   get_all_statuses_users: () => (/* binding */ get_all_statuses_users),
/* harmony export */   get_api_key: () => (/* binding */ get_api_key),
/* harmony export */   get_expiry: () => (/* binding */ get_expiry),
/* harmony export */   get_general_settings: () => (/* binding */ get_general_settings),
/* harmony export */   get_help_settings: () => (/* binding */ get_help_settings),
/* harmony export */   get_invitation_code_setttings: () => (/* binding */ get_invitation_code_setttings),
/* harmony export */   get_invited_users: () => (/* binding */ get_invited_users),
/* harmony export */   get_nua_codes: () => (/* binding */ get_nua_codes),
/* harmony export */   get_remaining_uses: () => (/* binding */ get_remaining_uses),
/* harmony export */   get_status: () => (/* binding */ get_status),
/* harmony export */   get_total_uses: () => (/* binding */ get_total_uses),
/* harmony export */   get_user_roles: () => (/* binding */ get_user_roles),
/* harmony export */   save_invitation_codes: () => (/* binding */ save_invitation_codes),
/* harmony export */   save_invite_codes: () => (/* binding */ save_invite_codes),
/* harmony export */   site_url: () => (/* binding */ site_url),
/* harmony export */   update_api_key: () => (/* binding */ update_api_key),
/* harmony export */   update_general_settings: () => (/* binding */ update_general_settings),
/* harmony export */   update_invitation_code: () => (/* binding */ update_invitation_code),
/* harmony export */   update_user_role: () => (/* binding */ update_user_role),
/* harmony export */   update_user_status: () => (/* binding */ update_user_status),
/* harmony export */   user_role_dummy: () => (/* binding */ user_role_dummy)
/* harmony export */ });

function action_status(current_status) {
  const statuses = {
    'pending': ['approve', 'deny'],
    'denied': ['approve'],
    'approved': ['deny']
  };
  return statuses[current_status] || [];
}
function siteUrl() {
  const site_location = siteDetail.siteUrl;
  console.log(site_location);
  return site_location;
}
function user_role_dummy() {
  let user_roles = [{
    'username': 'jhon',
    'current_role': 'Subscriber',
    'email_address': 'jhon@gmail.com',
    'requested_role': 'Owner'
  }, {
    'username': 'vince',
    'current_role': 'Customer',
    'email_address': 'vince@gmail.com',
    'requested_role': 'Administrator'
  }, {
    'username': 'martin',
    'current_role': 'Subscriber',
    'email_address': 'martin@hotmail.com',
    'requested_role': 'Editor'
  }, {
    'username': 'dean',
    'current_role': 'Contributer',
    'email_address': 'dean@hotmail.com',
    'requested_role': 'Administrator'
  }, {
    'username': 'lauren',
    'current_role': 'Subscriber',
    'email_address': 'lauren@help.com',
    'requested_role': 'Contributer'
  }];
  return user_roles;
}
function site_url() {
  return siteUrl();
}

// Get Nua Codes

const get_invited_users = async () => {
  const request_method = "get";
  try {
    const response = await fetch(`${NUARestAPI.get_invited_users + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const update_user_status = async (end_point = '', user_data = []) => {
  const endPoint = end_point;
  const userdata = user_data;
  try {
    const response = await fetch(`${NUARestAPI.update_users}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // Set content type to JSON
        'X-WP-Nonce': wpApiSettings.nonce
      },
      body: JSON.stringify(userdata)
    });
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const data = await response.json();
    return {
      message: 'Success',
      data: data
    };
  } catch (error) {
    return {
      message: 'Failed',
      error: error.message
    };
  }
};

//  fetch activity log

const get_activity_log = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_activity_log}`, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const data = await response.json();
    return {
      message: 'Success',
      data: data
    };
  } catch (error) {
    return {
      message: 'Failed',
      error: error.message
    };
  }
};
const update_general_settings = async ({
  generalSettings
}) => {
  const request_method = "update";
  try {
    const response = await fetch(`${NUARestAPI.general_settings + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce
      },
      body: JSON.stringify(generalSettings)
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_general_settings = async () => {
  const request_method = "get";
  try {
    const response = await fetch(`${NUARestAPI.general_settings + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_user_roles = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_user_roles}`, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const data = await response.json();
    return {
      message: 'Success',
      data: data
    };
  } catch (error) {
    return {
      message: 'Failed',
      error: error.message
    };
  }
};
const update_user_role = async ({
  userID,
  updateRole
}) => {
  const userdata = {
    user_id: userID,
    new_role: updateRole
  };
  try {
    const response = await fetch(`${NUARestAPI.update_user_role}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // Set content type to JSON
        'X-WP-Nonce': wpApiSettings.nonce
      },
      body: JSON.stringify(userdata)
    });
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_api_key = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_api_key}`, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const update_api_key = async ({
  apiKey
}) => {
  const api_key = {
    'api_key': apiKey
  };
  try {
    const response = await fetch(`${NUARestAPI.update_api_key}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
      },
      body: JSON.stringify(api_key)
    });
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const save_invite_codes = async ({
  endpoint,
  inviteCode
}) => {
  const end_point = endpoint;
  try {
    const response = await fetch(`${NUARestAPI.save_invitation_code}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
      },
      body: JSON.stringify(inviteCode)
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_invitation_code_setttings = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_invitation_code}`, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': wpApiSettings.nonce
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const update_invitation_code = async ({
  endpoint,
  updateCode
}) => {
  let request_url = "";
  if (endpoint == "update-invitation-code") {
    request_url = NUARestAPI.update_invitation_code;
  }
  try {
    const response = await fetch(`${request_url}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce
      },
      body: JSON.stringify(updateCode)
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const delete_invCode = async ({
  endpoint,
  code_id
}) => {
  let request_url = "";
  if (endpoint == "delete-invCode") {
    request_url = NUARestAPI.delete_invCode;
  }
  try {
    const response = await fetch(`${request_url}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce
      },
      body: JSON.stringify({
        code_id
      })
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};

// setting help

const get_help_settings = async () => {
  try {
    const response = await fetch(`${NUARestAPI.help_settings}`, {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};

// Invite Email End
// -----------------------------------------------
const get_all_statuses_users = async countFilter => {
  try {
    const response = await fetch(`${NUARestAPI.all_statuses_users + NUARestAPI.permalink_delimeter}filter_by=${countFilter}`, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const save_invitation_codes = async ({
  endpoint,
  inviteCode
}) => {
  let request_url = "";
  if (endpoint == "save-invitation-codes") {
    request_url = NUARestAPI.save_invitation_codes;
  }
  try {
    const response = await fetch(`${request_url}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce
      },
      body: JSON.stringify(inviteCode)
    });
    const data = await response.json();
    console.log("response: ", JSON.stringify(data));
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};

// Get Nua Codes

const get_nua_codes = async () => {
  const request_method = "get";
  try {
    const response = await fetch(`${NUARestAPI.get_nua_invite_codes + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_remaining_uses = async () => {
  const request_method = "get";
  try {
    const response = await fetch(`${NUARestAPI.get_remaining_uses + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_total_uses = async () => {
  const request_method = "get";
  try {
    const response = await fetch(`${NUARestAPI.get_total_uses + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_expiry = async () => {
  const request_method = "get";
  try {
    const response = await fetch(`${NUARestAPI.get_expiry + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    console.log(data);
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const get_status = async () => {
  const request_method = "get";
  try {
    const response = await fetch(`${NUARestAPI.get_status + NUARestAPI.permalink_delimeter}method=${request_method}`, {
      method: "PUT",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json"
      }
    });
    const data = await response.json();
    return {
      data: data
    };
  } catch (error) {
    return {
      error
    };
  }
};
const generateAPI = length => {
  const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let api_key = '';
  for (let i = 0; i < length; i++) {
    api_key += characters.charAt(Math.floor(Math.random() * characters.length));
  }
  return api_key;
};
const format_selected_values = ({
  valuesList
}) => {
  var selected_values = Object.entries(valuesList).map(([value, label]) => ({
    value: value,
    label: label
  }));
  return selected_values;
};
/******/ })()
;
//# sourceMappingURL=functions.js.map