class OdooConnectorFormHandler {

    /**
     * ---------------------------------
     * Class constructor
     * 
     * @param args An array of arguments
     * ---------------------------------
     */

    constructor( args ) {
        if( !this.validate( args ) ||
            !this.set_props( args ) ||
            !this.initiate( this ) ) {
            console.log( '%cCould not initiate OdooConnectorFormHandler!', 'color:#FF0000' );
        } else {
            console.log( '%cInitiated OdooConnectorFormHandler!', 'color:#00FF00' );
        }
    }

    /**
     * ---------------------------------
     * Validates class properties
     * 
     * @param args An array of arguments
     * 
     * @return Returns true on success
     *         Returns false on error
     * ---------------------------------
     */

    validate( args ) {
        if( typeof args.update_now  !== 'object' || args.update_now  === null ) {
            return false;
        }
        return true;
    }

    /**
     * ---------------------------------
     * Sets class properties
     * 
     * @param args An array of arguments
     * 
     * @return Returns true on success
     *         Returns false on error
     * ---------------------------------
     */

    set_props( args ) {
        this.update_now = args.update_now;
        return true;
    }

    /**
     * -------------------------------
     * Initiates everything
     * 
     * @return Returns true on success
     *         Returns false on error
     * -------------------------------
     */

    initiate( obj ) {
        obj.update_now.addEventListener( 'click', function( event ) {   
            obj.update_now.disabled = true;
            var request = new XMLHttpRequest();
            request.open( 'POST', opt.ajaxUrl, true );
            request.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8' );
            request.send( 'action=execute_odoo_cron_job' );
            request.onreadystatechange = function( event ) {
                if( this.readyState === 4 ) {
                    obj.update_now.disabled = false;
                    console.log( this.response );
                }
            };
        } );
        return true;
    }

}

window.addEventListener( 'load', function() {
    const odoo_connector_form_handler = new OdooConnectorFormHandler( {
        'update_now' : document.querySelector( '#odoo_connector_update_now' )
    } );
} );