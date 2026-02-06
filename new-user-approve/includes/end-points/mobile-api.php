<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'NUAF_Mobile_API' ) ) {

    class NUAF_Mobile_API { 

        private $FCM_token;

        public function __construct() {
            add_action( 'rest_api_init', array( $this, 'register_mobile_apis' ) );
            add_action( 'nua_app_push_notif', array( $this, 'sends_push_notification' ), 10, 1 );
        }

        public function register_mobile_apis() { 
            register_rest_route( 'nua-request', '/v1/connect-app', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'connect_app_callback' ),
                'permission_callback' => '__return_true'
            ) );

            register_rest_route( 'nua-request', '/v1/disconnect-app', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'disconnect_app_callback' ),
                'permission_callback' => '__return_true'
            ) );

            register_rest_route( 'nua-request', '/v1/check-license', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_plugin_license_status_callback' ),
                'permission_callback' => '__return_true'
            ) );

            register_rest_route( 'nua-request', '/v1/get-dashboard-data', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_dashboard_data' ),
                'permission_callback' => '__return_true'
            ) );

            register_rest_route( 'nua-request', '/v1/get-all-requests', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_all_user_requests' ),
                'permission_callback' => '__return_true'
            ) );

            register_rest_route( 'nua-request', '/v1/get-user-details', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_user_details_callback' ),
                'permission_callback' => '__return_true'
            ) );

            register_rest_route( 'nua-request', '/v1/user-approve', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'user_approve_request_callback' ),
                'permission_callback' => '__return_true'
            ) );

            register_rest_route( 'nua-request', '/v1/user-deny', array(
                'methods'  => 'GET',
                'callback' => array( $this, 'user_deny_request_callback' ),
                'permission_callback' => '__return_true'
            ) );
        }

        public function sends_push_notification( $user_id ) {

            $user_details = get_userdata( $user_id );

            $filtered_user_details = array(
                'ID'                => $user_details->ID,
                'user_name'         => $user_details->user_login,
                'display_name'      => $user_details->display_name,
                'user_email'        => $user_details->user_email,
                'roles'             => $user_details->roles,
                'nua_status'        => PW_New_User_Approve()->get_user_status( $user_details->ID ),
                'user_registered'   => $user_details->user_registered,
                'user_img'          => get_avatar_url( $user_details->ID )
            );

            $license_status = $this->nua_license_status();

            $response = wp_remote_post('https://app.newuserapprove.com/wp-json/nua-ms/v1/push-notification/', 
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json'
                    ),
                    'body'    => json_encode( array(
                        'site_url'          => site_url(),
                        'user_details'      => $filtered_user_details,
                        'license_status'    => $license_status
                    )),
                )
            );

            $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $response_body && $response_body['success'] ) {
                return true;
            } 

            return false;
        }

        private function nua_license_status() {

            $cache_key = 'nua_plugin_status_cache';
            $cached_status = get_transient( $cache_key );
            if ( $cached_status !== false ) {
                return $cached_status;
            }

            if ( nua_init_fs()->is_plan( 'nuabasic', true ) ) {
                set_transient( $cache_key, 'basic', DAY_IN_SECONDS );
                return 'basic';
            }

            if ( nua_init_fs()->is_plan( 'nuaprofessional', true ) ) {
                set_transient( $cache_key, 'professional', DAY_IN_SECONDS );
                return 'professional';
            }

            if ( nua_init_fs()->is_plan( 'nuabusiness', true ) ) {
                set_transient( $cache_key, 'business', DAY_IN_SECONDS );
                return 'business';
            }

            $response = wp_remote_post('https://app.newuserapprove.com//wp-json/nua-ms/v1/login-time', 
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                    ),
                    'body' => json_encode( array(
                        'site_url' => site_url()
                    )),
                )
            );

            $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $response_body && $response_body['success'] ) {
                $logged_date = new DateTime( $response_body['logged_time'] );
                $current_date = new DateTime('now');
                $diff = date_diff( $logged_date, $current_date );
                $days_used = $diff->format("%a");

                $status = ( $days_used <= 15 && $days_used >= 0 ) ? 'trial' : 'free';
                set_transient( $cache_key, $status, DAY_IN_SECONDS );
                return $status;
            } 

            set_transient( $cache_key, 'free', DAY_IN_SECONDS );
            return 'free';
        }

        public function get_plugin_license_status_callback() {
            delete_transient( 'nua_plugin_status_cache' );
            
            $status = $this->nua_license_status();

            return new WP_REST_Response( array(
                'success'   => true,
                'status'    => $status
            ), 200 );
        }

        private function create_secure_token( $FCM_token, $device_id ) { 
            $sucess = false;
            $token = md5( $FCM_token . $device_id );

            $all_tokens = get_option( 'nua_app_tokens', array() );

            if ( ! in_array( $token, $all_tokens ) ) {
                $all_tokens[] = $token;
                $sucess = update_option( 'nua_app_tokens', $all_tokens );
            }

            if ( $sucess ) {
                return $token;
            }

            return $sucess;
        }

        private function verify_secure_token( $FCM_token, $device_id ) { 
            if ( ! $FCM_token || ! $device_id ) {
                return false;
            }

            $expected_token = md5( $FCM_token . $device_id );

            $all_tokens = get_option( 'nua_app_tokens', array() );

            if ( in_array( $expected_token, $all_tokens ) ) {
                return true;
            }

            return false;
        }

        public function user_approve_request_callback( $request ) {
            $FCM_token = $request->get_header( 'FCMToken' );
            $device_id = $request->get_header( 'deviceId' );
            $user_id   = $request->get_header( 'userId' );

            if ( ! $FCM_token || ! $device_id || ! $user_id ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Missing required headers.',
                ), 400 );
            }

            $token_verified = $this->verify_secure_token( $FCM_token, $device_id );
            if ( ! $token_verified ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Token verification faild.',
                ), 400 );
            }

            $user = get_userdata( $user_id );

            if ( ! $user ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'User not found.',
                ), 400 );
            }

            $user_roles = $user->roles;

            if ( is_array( $user_roles ) && in_array( 'administrator', $user_roles, true ) ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'You cannot approve administrator.',
                ), 400 );
            }

            $plugin_status = $this->nua_license_status();

            PW_New_User_Approve()->approve_user( $user_id );

            return new WP_REST_Response( array(
                'success' => true,
                'status'  => $plugin_status,
            ), 200 );
        }

        public function user_deny_request_callback( $request ) {
            $FCM_token = $request->get_header( 'FCMToken' );
            $device_id = $request->get_header( 'deviceId' );
            $user_id   = $request->get_header( 'userId' );

            if ( ! $FCM_token || ! $device_id || ! $user_id ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Missing required headers.',
                ), 400 );
            }

            $token_verified = $this->verify_secure_token( $FCM_token, $device_id );
            if ( ! $token_verified ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Token verification faild.',
                ), 400 );
            }

            $user = get_userdata( $user_id );

            if ( ! $user ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'User not found.',
                ), 400 );
            }

            $user_roles = $user->roles;

            if ( is_array( $user_roles ) && in_array( 'administrator', $user_roles, true ) ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'You cannot approve administrator.',
                ), 400 );
            }

            $plugin_status = $this->nua_license_status();

			PW_New_User_Approve()->update_deny_status( $user_id ); 
			PW_New_User_Approve()->deny_user( $user_id );

            return new WP_REST_Response( array(
                'success' => true,
                'status'  => $plugin_status,
            ), 200 );
        }

        public function connect_app_callback( $request ) {
            $FCM_token = $request->get_header( 'FCMToken' );
            $auth_token = $request->get_header( 'authToken' );
            $device_name = $request->get_header( 'deviceName' );
            $system = $request->get_header( 'system' );
            $device_id =$request->get_header( 'deviceId' );

            if ( ! $FCM_token || ! $device_id || ! $auth_token || $auth_token !== get_option( "nua_app_auth_token" ) ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message' => 'Missing required headers or invalid auth token.',
                ), 400 );
            }

            $logged_in = wp_date('Y-m-d h:i:s A');

            $token = $this->create_secure_token( $FCM_token, $device_id );

            if ( ! $token ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message' => 'Error while creating token.',
                ), 400 );
            }

            $response = wp_remote_post('https://app.newuserapprove.com/wp-json/nua-ms/v1/register/', 
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json'
                    ),
                    'body'    => json_encode( array(
                        'site_url'      => site_url(),
                        'fcm_token'     => $FCM_token,
                        'device_id'     => $device_id,
                        'logged_in'     => $logged_in,
                        'device_name'   => $device_name,
                        'system'        => $system,
                        'token'         => $token
                    )),
                )
            );

            $response_body = json_decode( wp_remote_retrieve_body( $response ), true);

            if ( $response_body && $response_body['success'] ) {
                return new WP_REST_Response( array(
                    'success'   => true,
                    'loggedIn'  => $logged_in
                ), 200 );
            } else {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message' => 'Error while registering site and device.',
                ), 400 );
            }
        }

        public function disconnect_app_callback( $request ) {
            $FCM_token = $request->get_header( 'FCMToken' );
            $device_id = $request->get_header( 'deviceId' );
            $token = '';

            if ( ! $FCM_token || ! $device_id ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message' => 'Missing required headers.'
                ), 400 );
            }

            $token_verified = $this->verify_secure_token( $FCM_token, $device_id );
            if ( ! $token_verified ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Token verification faild.',
                ), 400 );
            } else {
                $token = md5( $FCM_token . $device_id );
                $all_tokens = get_option( 'nua_app_tokens', array() );

                $updated_tokens = array_diff( $all_tokens, array( $token ) );
                update_option( 'nua_app_tokens', $updated_tokens );
            }

            $response = wp_remote_post('https://app.newuserapprove.com/wp-json/nua-ms/v1/remove/', 
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json'
                    ),
                    'body'    => json_encode( array(
                        'fcm_token' => $FCM_token,
                        'token'     => $token
                    )),
                )
            );

            $response_body = json_decode( wp_remote_retrieve_body( $response ), true);

            if ( $response_body && $response_body['success'] ) {
                return new WP_REST_Response( array(
                    'success' => true,
                ), 200 );
            } else {
                return new WP_REST_Response( array(
                    'success' => false,
                ), 400 );
            }

        }

        public function get_user_details_callback( $request ) {
            $FCM_token = $request->get_header( 'FCMToken' );
            $device_id = $request->get_header( 'deviceId' );

            if ( ! $FCM_token || ! $device_id ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Missing required headers.',
                ), 400 );
            }

            $token_verified = $this->verify_secure_token( $FCM_token, $device_id );
            if ( ! $token_verified ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Token verification faild.',
                ), 400 );
            }

            $plugin_status = $this->nua_license_status();

            $user_id = $request->get_param( 'id' );
            $user_details = get_userdata( $user_id );

            $filtered_user_details = array(
                'ID'                => $user_details->ID,
                'user_name'         => $user_details->user_login,
                'display_name'      => $user_details->display_name,
                'user_email'        => $user_details->user_email,
                'roles'             => $user_details->roles,
                'nua_status'        => PW_New_User_Approve()->get_user_status( $user_details->ID ),
                'user_registered'   => $user_details->user_registered,
                'user_img'          => get_avatar_url( $user_details->ID )
            );

            return new WP_REST_Response( array(
                    'success'   => true,
                    'data'      => $filtered_user_details,
                    'status'    => $plugin_status
            ), 200 );
        }

        public function get_all_user_requests( $request ) {
            $FCM_token = $request->get_header( 'FCMToken' );
            $device_id = $request->get_header( 'deviceId' );

            if ( ! $FCM_token || ! $device_id ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Missing required headers.',
                ), 400 );
            }

            $token_verified = $this->verify_secure_token( $FCM_token, $device_id );
            if ( ! $token_verified ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Token verification faild.',
                ), 400 );
            }

            $plugin_status = $this->nua_license_status();

            $filter_by = $request->get_param( 'filter_by' );
            $filter_by = ! empty( $filter_by ) ? $filter_by : 'month';
            $number_limit = apply_filters( 'all_users_limit', -1 );
            $all_users = $this->nua_users_filter( $filter_by, $number_limit );

            return new WP_REST_Response( array(
                    'success'   => true,
                    'data'      => $all_users,
                    'status'    => $plugin_status,
            ), 200 );
        }

        public function get_dashboard_data( $request ) {
            $FCM_token = $request->get_header( 'FCMToken' );
            $device_id = $request->get_header( 'deviceId' );

            if ( ! $FCM_token || ! $device_id ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Missing required headers.',
                ), 400 );
            }

            $token_verified = $this->verify_secure_token( $FCM_token, $device_id );
            if ( ! $token_verified ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message'  => 'Token verification faild.',
                ), 400 );
            }

            $plugin_status = $this->nua_license_status();

            $filter_by = $request->get_param( 'filter_by' );
            $filter_by = ! empty( $filter_by ) ? $filter_by : 'month';
            $number_limit = apply_filters( 'analytics_users_limit', -1 );
            $results = $this->nua_users_filter( $filter_by, $number_limit );
            $total = 0;
            $pending = 0;
            $approved = 0;
            $denied  = 0;

            if ( ! empty( $results ) ) {
                foreach ( $results as  $user ) {
                    switch ( $user['nua_status'] ) {
                        case 'pending':
                            ++$pending;
                            break;
                        case 'approved':
                            ++$approved;
                            break;
                        case 'denied':
                            ++$denied;
                            break;
                        default:
                            break;
                    }
                }
                $total = absint( $pending ) + absint( $approved ) + absint( $denied );
            }

            $users = array(
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'denied' => $denied
            );

            return new WP_REST_Response( array(
                    'success'   => true,
                    'data'      => $users,
                    'status'    => $plugin_status
            ), 200 );

        }

        public function nua_users_filter( $filter_by = '', $number_limit = '' ) {

            $date_query = array();

            switch ( $filter_by ) {
                case 'today':
                    $date_query[] = array(
                        'after'     => 'today',
                        'inclusive' => true,
                        'column'    => 'user_registered',
                    );
                    break;
        
                case 'yesterday':
                    $date_query[] = array(
                        'after'     => 'yesterday',
                        'before'    => 'today',
                        'inclusive' => true,
                        'column'    => 'user_registered',
                    );
                    break;
        
                case 'week':
                    $date_query[] = array(
                        'after'     => 'last Sunday',
                        'inclusive' => true,
                        'column'    => 'user_registered',
                    );
                    break;
        
                case 'month':
                    $date_query[] = array(
                        'after'     => '30 days ago',
                        'inclusive' => true,
                        'column'    => 'user_registered',
                    );
                    break;
        
                default:
                    return null;
            }

            $args = array(
                'meta_query' => array(
                    array(
                        'key'     => 'pw_user_status',
                        'value'   => '',
                        'compare' => '!='
                    ),
                ),
                'date_query' => $date_query,
                'number'     => $number_limit,
                'orderby'    => 'user_registered',
                'order'      => 'DESC',
            );

            $results = new WP_User_Query( $args );

            if ( ! empty( $results->get_results() ) ) {
                $filtered_users = array();
                foreach ( $results->get_results() as $user ) {
                    $filtered_users[] = array(
                        'ID'           => $user->ID,
                        'display_name' => $user->display_name,
                        'user_email'   => $user->user_email,
                        'nua_status'   => PW_New_User_Approve()->get_user_status( $user->ID ),
                    );
                }

                return $filtered_users;
            } else {
                return array();
            }
        }

    }

    new NUAF_Mobile_API();

}