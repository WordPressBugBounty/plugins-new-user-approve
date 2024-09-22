<?php 

namespace NewUserApproveZapier;

class User {

    private static $_instance;

    /**
     * @version 1.0
     * @since 2.1
     */
    public static function get_instance()
    {
        if ( self::$_instance == null )
            self::$_instance = new self();

        return self::$_instance;
    }
    
    /**
     * @version 1.0
     * @since 2.1
     */
    public function __construct()
    {
        add_action( 'new_user_approve_user_approved', array( $this, 'user_approved' ) ); 
        add_action( 'new_user_approve_user_denied', array( $this, 'user_denied' ) ); 
        add_filter( 'new_user_approve_default_status', array( $this, 'user_pending' ), 999, 2 );
        
    }

    /**
     * @version 1.0
     * @since 2.5
     */
    public function user_pending( $status, $user_id )
    {
        if ( 'pending' == $status) {
            $this->update_user( 'nua_user_pending', $user_id );
        }
        return $status;
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function user_approved( $user )
    {
        if (!empty(get_user_meta( $user->ID, 'nua_invcode_used', true )) || !empty(get_user_meta( $user->ID, 'nua_wl_domain_used', true ))) {
            // user is auto approved through invitation code or whitelist
            return;
        }
        $this->update_user( 'nua_user_approved', $user->ID );
    }
    
    /**
     * @version 1.0
     * @since 2.1
     */
    public function user_denied( $user )
    {
        $this->update_user( 'nua_user_denied', $user->ID );
    }

    public function update_user( $option_name, $user_id )
    {
        // inserting the data into nua-zapier table
        nua_zapier_insert_log($option_name, $user_id );
    }

    /**
     * Making compatibility with NUA previous version 
     * Inserting the nua-zapier options users data to nua-zapier table 
     */
    public function nua_zap_compatible_legacy_options() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nua_zapier';
        $options_statuses = array('nua_user_pending',  'nua_user_approved', 'nua_user_denied');
        foreach( $options_statuses as $status ) {
            $user_data = get_option( $status );
            $option = array( "users_status" => $status );
            if( !empty( $user_data ) ) {
                foreach( $user_data as $key => $value ) {
                        $time = $value['time'];
                        unset( $value['time'] );
                        unset( $value['id'] );
                        $created_time = array('created_time' => $time );
                        $value = array_merge( $value ,$created_time ); 
                        $value = array_merge( $value ,$option );
                        $wpdb->insert( $table_name, $value );
                }
            }

        }
}
}

\NewUserApproveZapier\User::get_instance();