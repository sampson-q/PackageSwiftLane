<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************



// ===========================================================
// MODULE ALL TOOLS
// ===========================================================

$GLOBALS['cdp_error'] = '';


// ===========================================================
// USERS
// ===========================================================


function cdp_insertUserfp40f($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_users
        (
            username,
            name_off,
            password,
            userlevel,
            email,
            fname,
            lname,
            created,
            notes,
            phone,
            gender,
            newsletter,
            active
            
        )

        VALUES (
            :username,
            :branch_office,
            :password,
            :userlevel,
            :email,
            :fname,
            :lname,
            :created,
            :notes,
            :phone,
            :gender,
            :newsletter,
            :active 
        )');


    $db->bind(':username', $datos['username']);
    $db->bind(':branch_office', $datos['branch_office']);
    $db->bind(':password', $datos['password']);
    $db->bind(':userlevel', $datos['userlevel']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':created', $datos['created']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':gender', $datos['gender']);
    $db->bind(':newsletter', $datos['newsletter']);
    $db->bind(':active', $datos['active']);




    $result = $db->cdp_execute();
    $GLOBALS['cdp_error'] = $db->cdp_error();
    return $result;
}

function cdp_updateUserrx0xr($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_users SET
    
            name_off =:branch_office,
            password =:password,
            email =:email,
            document_type =:document_type,
            document_number =:document_number,
            fname =:fname,
            lname =:lname,
            notes =:notes,
            phone =:phone,
            userlevel =:userlevel,
            gender =:gender,
            newsletter =:newsletter,
            active =:active

            where id = :id
            
        ');


    $db->bind(':userlevel', $datos['userlevel']);
    $db->bind(':branch_office', $datos['branch_office']);
    $db->bind(':password', $datos['password']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':gender', $datos['gender']);
    $db->bind(':newsletter', $datos['newsletter']);
    $db->bind(':active', $datos['active']);
    $db->bind(':document_type', $datos['document_type']);
    $db->bind(':document_number', $datos['document_number']);
    $db->bind(':id', $datos['id']);




    $result = $db->cdp_execute();
    $GLOBALS['cdp_error'] = $db->cdp_error();
    return $result;
}



function cdp_getUserEdit4bozo($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_users WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}

function cdp_deleteUsersrhv5($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_users WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}




// ===========================================================
// USERS SINGUP
// ===========================================================



function cdp_insertUserSignUp($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_users
        (
            username,
            password,
            locker,
            userlevel,
            email,
            fname,
            lname,
            country,
            city,
            postal,
            created,
            address,
            terms
            
        )

        VALUES (
            :username,
            :password,
            :locker,
            :userlevel,
            :email,
            :fname,
            :lname,
            :country,
            :city,
            :postal,
            :created,
            :address,
            :terms
        )');


    $db->bind(':username', $datos['username']);
    $db->bind(':password', $datos['password']);
    $db->bind(':userlevel', $datos['userlevel']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':created', $datos['created']);
    $db->bind(':locker', $datos['locker']);
    $db->bind(':terms', $datos['terms']);
    $db->bind(':country', $datos['country']);
    $db->bind(':city', $datos['city']);
    $db->bind(':postal', $datos['postal']);
    $db->bind(':address', $datos['address']);




    return $db->cdp_execute();
}



function cdp_verifyEmailt1xle($email)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM cdb_users WHERE  email=:email");
    $db->bind(':email', $email);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_verifyCCtxtxtxtx($document_number)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM cdb_users WHERE  document_number=:document_number");
    $db->bind(':document_number', $document_number);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_updatePassword5glmh($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_users SET  password=:password where email=:email');
    $db->bind(':password', $datos['password']);
    $db->bind(':email', $datos['email']);

    return $db->cdp_execute();
}


// ===========================================================
// USERS TEMPLATE EMAIL
// ===========================================================

function cdp_getEmailTemplatesdg1i4($id)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM cdb_email_templates WHERE  id=:id");
    $db->bind(':id', $id);
    $db->cdp_execute();
    return $result = $db->cdp_registro();
}



// ===========================================================
// USERS TEMPLATE EMAIL
// ===========================================================

function cdp_getEmailTemplatesSMS($id)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM cdb_sms_templates WHERE  id=:id");
    $db->bind(':id', $id);
    $db->cdp_execute();
    return $result = $db->cdp_registro();
}


function cdp_getUserForEmail($email)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_users WHERE email=:email');

    $db->bind(':email', $email);

    $db->cdp_execute();

    return $data = $db->cdp_registro();
}


// ===========================================================
// USERS TEMPLATE SMS
// ===========================================================

function cdp_getsmsTemplates($id)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM cdb_sms_templates WHERE  id=:id");
    $db->bind(':id', $id);
    $db->cdp_execute();
    return  $db->cdp_registro();
}


// ===========================================================
// USERS DRIVERS
// ===========================================================

function cdp_insertDrivers1fcoe($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_users
        (
            username,
            name_off,
            password,
            userlevel,
            email,
            fname,
            lname,
            created,
            notes,
            phone,
            enrollment,
            vehiclecode,
            gender,
            newsletter,
            active
            
        )

        VALUES (
            :username,
            :branch_office,
            :password,
            :userlevel,
            :email,
            :fname,
            :lname,
            :created,
            :notes,
            :phone,
            :enrollment,
            :vehiclecode,
            :gender,
            :newsletter,
            :active 
        )');


    $db->bind(':username', $datos['username']);
    $db->bind(':branch_office', $datos['branch_office']);
    $db->bind(':password', $datos['password']);
    $db->bind(':userlevel', $datos['userlevel']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':created', $datos['created']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':gender', $datos['gender']);
    $db->bind(':newsletter', $datos['newsletter']);
    $db->bind(':active', $datos['active']);
    $db->bind(':enrollment', $datos['enrollment']);
    $db->bind(':vehiclecode', $datos['vehiclecode']);




    return $db->cdp_execute();
}





function cdp_updateDrivers($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_users SET
    
            password =:password,
            email =:email,
            fname =:fname,
            lname =:lname,
            notes =:notes,
            phone =:phone,
            gender =:gender,
            newsletter =:newsletter,
            active =:active,
            enrollment=:enrollment,
            vehiclecode=:vehiclecode

            where id = :id
            
        ');


    $db->bind(':password', $datos['password']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':gender', $datos['gender']);
    $db->bind(':newsletter', $datos['newsletter']);
    $db->bind(':active', $datos['active']);
    $db->bind(':id', $datos['id']);
    $db->bind(':enrollment', $datos['enrollment']);
    $db->bind(':vehiclecode', $datos['vehiclecode']);




    return $db->cdp_execute();
}





// ===========================================================
// UPDATE CONFIG GENERAL
// ===========================================================

function cdp_updateConfigGeneral0gqr5($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            language =:language,
            currency =:currency,
            for_currency =:for_currency,
            for_symbol =:for_symbol,
            for_decimal =:for_decimal,
            cformat =:cformat,
            dec_point =:dec_point,
            thousands_sep =:thousands_sep,
            timezone =:timezone            
            
        ');

    $db->bind(':language', $datos['language']);
    $db->bind(':currency', $datos['currency']);
    $db->bind(':for_currency', $datos['for_currency']);
    $db->bind(':for_symbol', $datos['for_symbol']);
    $db->bind(':for_decimal', $datos['for_decimal']);
    $db->bind(':cformat', $datos['cformat']);
    $db->bind(':dec_point', $datos['dec_point']);
    $db->bind(':thousands_sep', $datos['thousands_sep']);
    $db->bind(':timezone', $datos['timezone']);




    return $db->cdp_execute();
}




// ===========================================================
// UPDATE SEO CONFIG
// ===========================================================

function cdp_updateSeoConfig($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_meta_seo SET
            meta_description = :meta_description,
            meta_keywords = :meta_keywords,
            og_title = :og_title,
            og_description = :og_description,
            og_type = :og_type,
            og_url = :og_url,
            og_image = :og_image
        WHERE id = 1'); // O reemplázalo por el id necesario

    $db->bind(':meta_description', $datos['meta_description']);
    $db->bind(':meta_keywords', $datos['meta_keywords']);
    $db->bind(':og_title', $datos['og_title']);
    $db->bind(':og_description', $datos['og_description']);
    $db->bind(':og_type', $datos['og_type']);
    $db->bind(':og_url', $datos['og_url']);
    $db->bind(':og_image', $datos['og_image']);

    return $db->cdp_execute();
}



// ===========================================================
// UPDATE CONFIG SYSTEM
// ===========================================================

function cdp_updateConfigSystemytdb1($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            site_name =:site_name,
            site_url =:site_url,            
            c_nit =:c_nit,
            c_phone =:c_phone,
            cell_phone =:cell_phone,
            c_address =:c_address,
            locker_address =:locker_address,
            c_country =:c_country,
            c_city =:c_city,
            c_postal =:c_postal,
            site_email =:site_email,
            reg_allowed =:reg_allowed,
            reg_verify =:reg_verify,
            notify_admin =:notify_admin,
            auto_verify =:auto_verify,
            code_number_locker =:code_number_locker,
            digit_random_locker =:digit_random_locker,
            prefix_locker =:prefix_locker          
            
        ');


    $db->bind(':site_name', $datos['site_name']);
    $db->bind(':site_url', $datos['site_url']);
    $db->bind(':c_nit', $datos['c_nit']);
    $db->bind(':c_phone', $datos['c_phone']);
    $db->bind(':cell_phone', $datos['cell_phone']);
    $db->bind(':c_address', $datos['c_address']);
    $db->bind(':locker_address', $datos['locker_address']);
    $db->bind(':c_country', $datos['c_country']);
    $db->bind(':c_city', $datos['c_city']);
    $db->bind(':c_postal', $datos['c_postal']);
    $db->bind(':site_email', $datos['site_email']);
    $db->bind(':reg_allowed', $datos['reg_allowed']);
    $db->bind(':reg_verify', $datos['reg_verify']);
    $db->bind(':notify_admin', $datos['notify_admin']);
    $db->bind(':auto_verify', $datos['auto_verify']);
    $db->bind(':code_number_locker', $datos['code_number_locker']);
    $db->bind(':digit_random_locker', $datos['digit_random_locker']);
    $db->bind(':prefix_locker', $datos['prefix_locker']);



    return $db->cdp_execute();
}






// ===========================================================
// UPDATE CONFIG TAXES
// ===========================================================

function cdp_updateConfigTaxesx4spw($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            tax =:tax,
            insurance =:insurance,
            value_weight =:value_weight,
            weight_p =:weight_p,
            meter =:meter,
            units =:units,
            min_cost_tax=:min_cost_tax,
            c_tariffs =:c_tariffs, 
            min_cost_declared_tax =:min_cost_declared_tax,
            declared_tax = :declared_tax                 
            
        ');


    $db->bind(':tax', $datos['tax']);
    $db->bind(':insurance', $datos['insurance']);
    $db->bind(':value_weight', $datos['value_weight']);
    $db->bind(':weight_p', $datos['weight_p']);
    $db->bind(':min_cost_tax', $datos['min_cost_tax']);
    $db->bind(':meter', $datos['meter']);
    $db->bind(':units', $datos['units']);
    $db->bind(':c_tariffs', $datos['c_tariffs']);
    $db->bind(':declared_tax', $datos['declared_tax']);
    $db->bind(':min_cost_declared_tax', $datos['min_cost_declared_tax']);



    return $db->cdp_execute();
}


// ===========================================================
// UPDATE CONFIG INFO SHIP DEFAULT
// ===========================================================

function cdp_updateConfigInfoShipDefault4xiw0($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_info_ship_default SET
    
            logistics_default1 =:logistics_default1,
            packaging_default2 =:packaging_default2,
            courier_default3 =:courier_default3,
            service_default4 =:service_default4,
            time_default5 =:time_default5,
            pay_default6 =:pay_default6, 
            payment_default7 =:payment_default7,
            status_default8 = :status_default8                 
            
        ');


    $db->bind(':logistics_default1', $datos['logistics_default1']);
    $db->bind(':packaging_default2', $datos['packaging_default2']);
    $db->bind(':courier_default3', $datos['courier_default3']);
    $db->bind(':service_default4', $datos['service_default4']);
    $db->bind(':time_default5', $datos['time_default5']);
    $db->bind(':pay_default6', $datos['pay_default6']);
    $db->bind(':payment_default7', $datos['payment_default7']);
    $db->bind(':status_default8', $datos['status_default8']);



    return $db->cdp_execute();
}



// ===========================================================
// UPDATE CONFIG EMAIL SMTP
// ===========================================================

function cdp_updateConfigSmtpemailr2g61($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            mailer =:mailer,
            smtp_names =:smtp_names,
            email_address =:email_address,
            smtp_host =:smtp_host,
            smtp_user =:smtp_user,
            smtp_password =:smtp_password,
            smtp_port =:smtp_port,
            smtp_secure =:smtp_secure
            
        ');


    $db->bind(':mailer', $datos['mailer']);
    $db->bind(':smtp_names', $datos['smtp_names']);
    $db->bind(':email_address', $datos['email_address']);
    $db->bind(':smtp_host', $datos['smtp_host']);
    $db->bind(':smtp_user', $datos['smtp_user']);
    $db->bind(':smtp_password', $datos['smtp_password']);
    $db->bind(':smtp_port', $datos['smtp_port']);
    $db->bind(':smtp_secure', $datos['smtp_secure']);



    return $db->cdp_execute();
}



// ===========================================================
// UPDATE CONFIG TRACK INVOICE
// ===========================================================

function cdp_updateConfigTrackInvoicepn8vt($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            interms =:interms,
            signing_customer =:signing_customer,
            signing_company =:signing_company,
            prefix =:prefix,
            track_digit =:track_digit,
            code_number =:code_number,
            digit_random =:digit_random,
            prefix_consolidate =:prefix_consolidate,
            track_consolidate =:track_consolidate,
            prefix_online_shopping = :prefix_online_shopping
        ');


    $db->bind(':interms', $datos['interms']);
    $db->bind(':signing_customer', $datos['signing_customer']);
    $db->bind(':signing_company', $datos['signing_company']);
    $db->bind(':prefix', $datos['prefix']);
    $db->bind(':track_digit', $datos['track_digit']);
    $db->bind(':code_number', $datos['code_number']);
    $db->bind(':digit_random', $datos['digit_random']);
    $db->bind(':prefix_consolidate', $datos['prefix_consolidate']);
    $db->bind(':track_consolidate', $datos['track_consolidate']);
    $db->bind(':prefix_online_shopping', $datos['prefix_online_shopping']);


    return $db->cdp_execute();
}





// ===========================================================
// UPDATE CONFIG PAYMENT
// ===========================================================

function cdp_updateConfigPaymentgowxl($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            account_paypal =:account_paypal,
            client_id =:client_id            
        ');

    $db->bind(':client_id', $datos['client_id']);
    $db->bind(':account_paypal', $datos['account_paypal']);




    return $db->cdp_execute();
}

// ===========================================================
// UPDATE CONFIG API GOOGLE 
// ===========================================================

function cdp_updateConfigApiGoogle($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            longitude =:longitude,
            latitude =:latitude,          
            apikey =:apikey           
        ');

    $db->bind(':apikey', $datos['apikey']);
    $db->bind(':latitude', $datos['latitude']);
    $db->bind(':longitude', $datos['longitude']);

    return $db->cdp_execute();
}





// ===========================================================
// UPDATE STATUS SMSTWILIO
// ===========================================================

function cdp_updateStatusTwilo($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_textsms SET    
                     
            active_twi =:active_twi
            where  id=:id      
        ');

    $db->bind(':active_twi', $datos['active_twi']);
    $db->bind(':id', $datos['id']);



    return $db->cdp_execute();
}



// ===========================================================
// UPDATE TEMPLATES EMAIL
// ===========================================================
function cdp_updateTemplatesEmail($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_email_templates SET

          name=:name,
          subject=:subject,
          body=:body,
          help=:help

         where id=:id');


    $db->bind(':name', $datos['name']);
    $db->bind(':subject', $datos['subject']);
    $db->bind(':body', $datos['body']);
    $db->bind(':help', $datos['help']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}




// ===========================================================
// UPDATE TEMPLATES SMS
// ===========================================================
function cdp_updateTemplatesSMSc2rbi($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_sms_templates SET

          name=:name,
          subject=:subject,
          body=:body,
          help=:help

         where id=:id');


    $db->bind(':name', $datos['name']);
    $db->bind(':subject', $datos['subject']);
    $db->bind(':body', $datos['body']);
    $db->bind(':help', $datos['help']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


// ===========================================================
// CRUD SHIP LINE
// ===========================================================



function cdp_lineExists($ship_line, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_shipping_line WHERE  ship_line=:ship_line $where");
    $db->bind(':ship_line', $ship_line);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}




function cdp_insertShipLine($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_shipping_line
        (
            ship_line,
            detail
            
        )

        VALUES (

            :ship_line,
            :detail
            
        )');


    $db->bind(':ship_line', $datos['ship_line']);
    $db->bind(':detail', $datos['detail']);

    return $db->cdp_execute();
}



function cdp_getShiplineEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_shipping_line WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_updateShipLine($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_shipping_line SET    
                     
            ship_line =:ship_line,
            detail =:detail

            where  id=:id      
        ');


    $db->bind(':ship_line', $datos['ship_line']);
    $db->bind(':detail', $datos['detail']);
    $db->bind(':id', $datos['id']);



    return $db->cdp_execute();
}



function cdp_deleteShipline($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_shipping_line WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


// ===========================================================
// CRUD INCOTERMS
// ===========================================================


function cdp_incoExists($inco_name, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_incoterm WHERE  inco_name=:inco_name $where");
    $db->bind(':inco_name', $inco_name);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}



function cdp_insertIncoterms($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_incoterm
        (
            inco_name,
            detail
            
        )

        VALUES (

            :inco_name,
            :detail
            
        )');


    $db->bind(':inco_name', $datos['inco_name']);
    $db->bind(':detail', $datos['detail']);

    return $db->cdp_execute();
}


function cdp_getSIncotermsEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_incoterm WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_updateIncoterms($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_incoterm SET    
                     
            inco_name =:inco_name,
            detail =:detail

            where  id=:id      
        ');


    $db->bind(':inco_name', $datos['inco_name']);
    $db->bind(':detail', $datos['detail']);
    $db->bind(':id', $datos['id']);



    return $db->cdp_execute();
}



function cdp_deleteIncoterms($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_incoterm WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}

// ===========================================================
// CRUD OFFICES
// ===========================================================


function cdp_officeExistsjmbj1($name_off, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_offices WHERE  name_off=:name_off $where");
    $db->bind(':name_off', $name_off);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}



function cdp_codeofficeExists($code_off, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_offices WHERE  code_off=:code_off $where");
    $db->bind(':code_off', $code_off);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}




function cdp_insertOffices($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_offices
        (
            name_off,
            code_off,
            address,
            city,
            phone_off            
        )

        VALUES (

            :name_off,
            :code_off,
            :address,
            :city,
            :phone_off            
        )');




    $db->bind(':name_off', $datos['name_off']);
    $db->bind(':code_off', $datos['code_off']);
    $db->bind(':address', $datos['address']);
    $db->bind(':city', $datos['city']);
    $db->bind(':phone_off', $datos['phone_off']);

    return $db->cdp_execute();
}


function cdp_getOfficesEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_offices WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_updateOffices($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_offices SET    
                     
            name_off =:name_off,
            code_off =:code_off,
            address =:address,
            city =:city,
            phone_off =:phone_off
            where  id=:id      
        ');


    $db->bind(':name_off', $datos['name_off']);
    $db->bind(':code_off', $datos['code_off']);
    $db->bind(':address', $datos['address']);
    $db->bind(':city', $datos['city']);
    $db->bind(':phone_off', $datos['phone_off']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}



function cdp_deleteOffices($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_offices WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}





// ===========================================================
// CRUD ROLES
// ===========================================================


function cdp_rolesExistsjmbj12($role_name, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and role_id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_user_roles WHERE  role_name=:role_name $where");
    $db->bind(':role_name', $role_name);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_getRolesEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_user_roles WHERE role_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_insertRoles($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_user_roles
        (
            role_name,
            description,
            rol_active           
        )

        VALUES (

            :role_name,
            :description,
            :rol_active         
        )');




    $db->bind(':role_name', $datos['role_name']);
    $db->bind(':description', $datos['description']);
    $db->bind(':rol_active', $datos['rol_active']);

    return $db->cdp_execute();
}


function cdp_updateRole($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_user_roles SET    
        role_name = :role_name,
        description = :description,
        rol_active = :rol_active
        WHERE role_id = :id'
    );

    $db->bind(':role_name', $datos['role_name']);
    $db->bind(':description', $datos['description']);
    $db->bind(':rol_active', $datos['rol_active']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_deleteRoles($roleId)
{
    $db = new Conexion;

    try {
        // Inicia una transacción para garantizar la consistencia
        $db->dbh->beginTransaction();

        // Eliminar permisos asociados al rol
        $db->cdp_query("DELETE FROM cdb_user_role_permissions WHERE role_id = :role_id");
        $db->bind(':role_id', $roleId);
        if (!$db->cdp_execute()) {
            throw new Exception("Error al eliminar permisos asociados al rol.");
        }

        // Eliminar el rol
        $db->cdp_query("DELETE FROM cdb_user_roles WHERE role_id = :role_id");
        $db->bind(':role_id', $roleId);
        if (!$db->cdp_execute()) {
            throw new Exception("Error al eliminar el rol.");
        }

        // Confirmar transacción
        $db->dbh->commit();
        return true;

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $db->dbh->rollBack();
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}



// ===========================================================
// CRUD MODULE SYSTEM
// ===========================================================

function cdp_getModulesEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_user_module_permissions WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


// ===========================================================
// CRUD BRANCHOFFICES
// ===========================================================

/**
 * Obtiene el id de sucursal/agencia (cdb_branchoffices.id) para filtrar datos por agencia (userlevel 6).
 * El usuario agencia tiene name_off = nombre de sucursal; se busca por name_branch en cdb_branchoffices.
 * @param string $name_off name_off del usuario (sucursal/agencia)
 * @return int id de cdb_branchoffices o 0 si no hay coincidencia (filtrar por 0 muestra nada para agency)
 */
function cdp_getAgencyBranchIdForUser($name_off)
{
    if (empty($name_off)) {
        return 0;
    }
    $db = new Conexion;
    $db->cdp_query('SELECT id FROM cdb_branchoffices WHERE name_branch = :name_off LIMIT 1');
    $db->bind(':name_off', $name_off);
    $db->cdp_execute();
    $row = $db->cdp_registro();
    return $row ? (int)$row->id : 0;
}

/**
 * Contexto de agencia del usuario en sesión. Una sola fuente de verdad para multi-tenant.
 * Usa cdb_users.agency_id (no name_off).
 * @return array{is_restricted: bool, agency_id: int|null}
 */
function cdp_getAgencyContext()
{
    $out = ['is_restricted' => false, 'agency_id' => null];
    if (empty($_SESSION['userid'])) {
        return $out;
    }
    $db = new Conexion;
    $db->cdp_query('SELECT userlevel, agency_id FROM cdb_users WHERE id = :id LIMIT 1');
    $db->bind(':id', (int)$_SESSION['userid']);
    $db->cdp_execute();
    $row = $db->cdp_registro();
    if (!$row) {
        return $out;
    }
    $ul = (int)$row->userlevel;
    $aid = isset($row->agency_id) && $row->agency_id !== '' ? (int)$row->agency_id : null;
    if ($aid !== null && $aid <= 0) {
        $aid = null;
    }
    if ($ul === 9) {
        return $out;
    }
    if ($ul === 6) {
        return ['is_restricted' => true, 'agency_id' => $aid];
    }
    if (in_array($ul, [2, 4], true) && $aid !== null) {
        return ['is_restricted' => true, 'agency_id' => $aid];
    }
    return $out;
}

function cdp_branchofficeExistsr9ufr($name_branch, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_branchoffices WHERE  name_branch=:name_branch $where");
    $db->bind(':name_branch', $name_branch);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertBranchOffices($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_branchoffices
        (
            name_branch,
            branch_address,
            branch_city,
            phone_branch
        )

        VALUES (

            :name_branch,
            :branch_address,
            :branch_city,
            :phone_branch
        )');



    $db->bind(':name_branch', $datos['name_branch']);
    $db->bind(':branch_address', $datos['branch_address']);
    $db->bind(':branch_city', $datos['branch_city']);
    $db->bind(':phone_branch', $datos['phone_branch']);

    return $db->cdp_execute();
}


function cdp_getBranchOfficesEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_branchoffices WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_updateBranchOffices($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_branchoffices SET    
                     
            name_branch =:name_branch,
            branch_address =:branch_address,
            branch_city =:branch_city,
            phone_branch =:phone_branch

            where  id=:id      
        ');


    $db->bind(':name_branch', $datos['name_branch']);
    $db->bind(':branch_address', $datos['branch_address']);
    $db->bind(':branch_city', $datos['branch_city']);
    $db->bind(':phone_branch', $datos['phone_branch']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}



function cdp_deleteBranchOffices($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_branchoffices WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


// ===========================================================
// CRUD COURIER COMPANY
// ===========================================================

function cdp_courierExists9y45g($name_com, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_courier_com WHERE  name_com=:name_com $where");
    $db->bind(':name_com', $name_com);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertCourierCompany($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_courier_com
        (
            name_com,
            address_cou,
            phone_cou,
            country_cou,
            city_cou,
            postal_cou
        )

        VALUES (

            :name_com,
            :address_cou,
            :phone_cou,
            :country_cou,
            :city_cou,
            :postal_cou
        )');



    $db->bind(':name_com', $datos['name_com']);
    $db->bind(':address_cou', $datos['address_cou']);
    $db->bind(':phone_cou', $datos['phone_cou']);
    $db->bind(':country_cou', $datos['country_cou']);
    $db->bind(':city_cou', $datos['city_cou']);
    $db->bind(':postal_cou', $datos['postal_cou']);

    return $db->cdp_execute();
}


function cdp_getCourierCompanyEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_courier_com WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_updateCourierCompany($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_courier_com SET    
                     
            name_com =:name_com,
            address_cou =:address_cou,
            phone_cou =:phone_cou,
            country_cou =:country_cou,
            city_cou =:city_cou,
            postal_cou =:postal_cou

            where  id=:id      
        ');


    $db->bind(':name_com', $datos['name_com']);
    $db->bind(':address_cou', $datos['address_cou']);
    $db->bind(':phone_cou', $datos['phone_cou']);
    $db->bind(':country_cou', $datos['country_cou']);
    $db->bind(':city_cou', $datos['city_cou']);
    $db->bind(':postal_cou', $datos['postal_cou']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}



function cdp_deleteCourierCompany($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_courier_com WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}





// ===========================================================
// CRUD DELIVERY TIME
// ===========================================================

function cdp_DelitimeExists($delitime, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_delivery_time WHERE  delitime=:delitime $where");
    $db->bind(':delitime', $delitime);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertDeliverytime($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_delivery_time
        (
            delitime,
            detail            
        )

        VALUES (

            :delitime,
            :detail
            
        )');




    $db->bind(':detail', $datos['detail']);
    $db->bind(':delitime', $datos['delitime']);

    return $db->cdp_execute();
}


function cdp_updateDeliverytime($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_delivery_time SET    
                     
            detail =:detail,
            delitime =:delitime          

            where  id=:id      
        ');


    $db->bind(':detail', $datos['detail']);
    $db->bind(':delitime', $datos['delitime']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_getDeliveryTimeEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_delivery_time WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_deleteDeliverytime($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_delivery_time WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


// ===========================================================
// CRUD SHIPPING MODE
// ===========================================================

function cdp_statusExists($ship_mode, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE  ship_mode=:ship_mode $where");
    $db->bind(':ship_mode', $ship_mode);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertShippinMode($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_shipping_mode
        (
            ship_mode,
            detail            
        )

        VALUES (

            :ship_mode,
            :detail
            
        )');




    $db->bind(':detail', $datos['detail']);
    $db->bind(':ship_mode', $datos['ship_mode']);

    return $db->cdp_execute();
}


function cdp_updateShippinMode($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_shipping_mode SET    
                     
            detail =:detail,
            ship_mode =:ship_mode          

            where  id=:id      
        ');


    $db->bind(':detail', $datos['detail']);
    $db->bind(':ship_mode', $datos['ship_mode']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_getShippinModeEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_shipping_mode WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_getShipRateEdit($rates_id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_add_ship_rates WHERE rates_id=:rates_id');

    $db->bind(':rates_id', $rates_id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_deleteShippinMode($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_shipping_mode WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}



// ===========================================================
// CRUD STATUS/ STYLES COURIER
// ===========================================================
function cdp_statusCourierExists($mod_style, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_styles WHERE  mod_style=:mod_style $where");
    $db->bind(':mod_style', $mod_style);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_getCourierstatusApi($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_styles WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}


function cdp_colorStatusCourierExists($color, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_styles WHERE  color=:color $where");
    $db->bind(':color', $color);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}

function cdp_insertStatusCourier($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_styles
        (
            mod_style,
            detail,
            color,
            status_type
        )

        VALUES (
            :mod_style,
            :detail,
            :color,
            :status_type
        )');



    $db->bind(':detail', $datos['detail']);
    $db->bind(':mod_style', $datos['mod_style']);
    $db->bind(':color', $datos['color']);
    $db->bind(':status_type', $datos['status_type']);

    return $db->cdp_execute();
}


function cdp_updateStatusCourier($datos) {
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_styles SET
            detail =:detail,
            mod_style =:mod_style,
            color = :color,
            status_type = :status_type

            where  id=:id      
        ');


    $db->bind(':detail', $datos['detail']);
    $db->bind(':mod_style', $datos['mod_style']);
    $db->bind(':color', $datos['color']);
    $db->bind(':status_type', $datos['status_type']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_getStatusCourierEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_styles WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}




function cdp_deleteStatusCourier($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_styles WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}

// ===========================================================
//  CRUD PAYMENT METHODS
// ===========================================================

function cdp_paymentMethodExists($name_pay, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_met_payment WHERE  name_pay=:name_pay $where");
    $db->bind(':name_pay', $name_pay);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}

function cdp_paymentMethods2Exists($label, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_payment_methods WHERE  label=:label $where");
    $db->bind(':label', $label);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}




function cdp_insertPaymentMethods2($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_payment_methods
        (
            label,
            days
        )

        VALUES (

            :met_payment,
            :detail
            
        )');




    $db->bind(':met_payment', $datos['met_payment']);
    $db->bind(':detail', $datos['detail']);


    return $db->cdp_execute();
}


function cdp_updatePaymentMethod_cash($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_met_payment SET    
                     
            name_pay =:name_pay,
            detail_pay =:detail_pay,
            is_active =:is_active     

            where  id=:id      
        ');


    $db->bind(':name_pay', $datos['name_pay']);
    $db->bind(':detail_pay', $datos['detail_pay']);
    $db->bind(':is_active', $datos['is_active']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}

function cdp_updatePaymentMethod_paypal($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_met_payment SET    
                     
            name_pay =:name_pay,
            detail_pay =:detail_pay,
            paypal_client_id =:paypal_client_id,
            is_active =:is_active     

            where  id=:id      
        ');


    $db->bind(':name_pay', $datos['name_pay']);
    $db->bind(':detail_pay', $datos['detail_pay']);
    $db->bind(':paypal_client_id', $datos['paypal_client_id']);
    $db->bind(':is_active', $datos['is_active']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}

function cdp_updatePaymentMethod_stripe($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_met_payment SET    
                     
            name_pay =:name_pay,
            detail_pay =:detail_pay,
            public_key =:public_key,
            secret_key =:secret_key,
            is_active =:is_active     

            where  id=:id      
        ');


    $db->bind(':name_pay', $datos['name_pay']);
    $db->bind(':detail_pay', $datos['detail_pay']);
    $db->bind(':public_key', $datos['public_key']);
    $db->bind(':secret_key', $datos['secret_key']);
    $db->bind(':is_active', $datos['is_active']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}

function cdp_updatePaymentMethod_paystack($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_met_payment SET    
                     
            name_pay =:name_pay,
            detail_pay =:detail_pay,
            public_key =:public_key,
            secret_key =:secret_key,
            is_active =:is_active     

            where  id=:id      
        ');


    $db->bind(':name_pay', $datos['name_pay']);
    $db->bind(':detail_pay', $datos['detail_pay']);
    $db->bind(':public_key', $datos['public_key']);
    $db->bind(':secret_key', $datos['secret_key']);
    $db->bind(':is_active', $datos['is_active']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}

function cdp_updatePaymentMethod_wire($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_met_payment SET    
                     
            name_pay =:name_pay,
            detail_pay =:detail_pay,
            is_active =:is_active     

            where  id=:id      
        ');


    $db->bind(':name_pay', $datos['name_pay']);
    $db->bind(':detail_pay', $datos['detail_pay']);
    $db->bind(':is_active', $datos['is_active']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_updatePaymentMethods2($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_payment_methods SET    
                     
            label =:met_payment,
            days =:detail    

            where  id=:id      
        ');


    $db->bind(':met_payment', $datos['met_payment']);
    $db->bind(':detail', $datos['detail']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}



function cdp_getPaymentMethod2Edit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_payment_methods WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_getPaymentMethodAPIEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_met_payment WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}

function cdp_deletePaymentMode($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_met_payment WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function cdp_deletePaymentMethod2($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_payment_methods WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}

// ==========================================================
// CRUD PACKAGING
// ===========================================================

function cdp_packExists($name_pack, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_packaging WHERE  name_pack=:name_pack $where");
    $db->bind(':name_pack', $name_pack);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertPackaging($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_packaging
        (
            name_pack,
            detail_pack
        )

        VALUES (

            :name_pack,
            :detail_pack
            
        )');




    $db->bind(':name_pack', $datos['name_pack']);
    $db->bind(':detail_pack', $datos['detail_pack']);


    return $db->cdp_execute();
}


function cdp_updatePackaging($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_packaging SET    
                     
            detail_pack =:detail_pack,
            name_pack =:name_pack     

            where  id=:id      
        ');


    $db->bind(':name_pack', $datos['name_pack']);
    $db->bind(':detail_pack', $datos['detail_pack']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_getPackagingEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_packaging WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_deletePackaging($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_packaging WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


// ==========================================================
// CRUD CATEGORY ITEM
// ===========================================================

function cdp_itemExists($name_item, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_category WHERE  name_item=:name_item $where");
    $db->bind(':name_item', $name_item);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertCategoryItem($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_category
        (
            name_item,
            detail_item
        )

        VALUES (

            :name_item,
            :detail_item
            
        )');




    $db->bind(':name_item', $datos['name_item']);
    $db->bind(':detail_item', $datos['detail_item']);


    return $db->cdp_execute();
}


function cdp_updateCategory($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_category SET    
                     
            detail_item =:detail_item,
            name_item =:name_item     

            where  id=:id      
        ');


    $db->bind(':name_item', $datos['name_item']);
    $db->bind(':detail_item', $datos['detail_item']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_getCategoryEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_category WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_deleteCategory($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_category WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}





function cdp_getCourierPrint($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_add_order WHERE order_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_getConsolidatePrint($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_consolidate WHERE consolidate_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_getConsolidatePackage($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_consolidate_packages WHERE consolidate_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_getCourierPrintMultiple($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_add_order WHERE order_no=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_getPackagePrintMultiple($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_customers_packages WHERE order_no=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}

function cdp_getConsolidatePrintMultiple($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_consolidate WHERE c_no=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function cdp_deleteCourier($datos)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_add_order SET                
           
            status_courier ='21', reason_cancel=:reason_cancel        

            where  order_id=:id      
        ");



    $db->bind(':id', $datos['id']);
    $db->bind(':reason_cancel', $datos['message']);

    return $db->cdp_execute();
}

function cdp_deleteFullCourier($datos)
{

    $db = new Conexion;


    $db->cdp_query('DELETE cdb_add_order, cdb_add_order_item, cdb_address_shipments, cdb_courier_track FROM cdb_add_order
        INNER JOIN cdb_add_order_item 
        INNER JOIN cdb_address_shipments
        INNER JOIN cdb_courier_track


        ON cdb_add_order.order_id = cdb_add_order_item.order_id 
        AND cdb_address_shipments.order_id = cdb_add_order.order_id
        AND cdb_courier_track.order_id = cdb_add_order.order_id


        WHERE cdb_add_order.order_id=:id');

    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_deleteFullCourierpackage($datos)
{

    $db = new Conexion;


    $db->cdp_query('DELETE cdb_customers_packages, cdb_customers_packages_detail, cdb_address_shipments, cdb_courier_track FROM cdb_customers_packages
        INNER JOIN cdb_customers_packages_detail 
        INNER JOIN cdb_address_shipments
        INNER JOIN cdb_courier_track


        ON cdb_customers_packages.order_id = cdb_customers_packages_detail.order_id 
        AND cdb_courier_track.order_id = cdb_customers_packages.order_id
        AND cdb_address_shipments.order_id = cdb_customers_packages.order_id

        WHERE cdb_customers_packages.order_id=:id');

    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}

function cdp_deleteConsolidate($datos)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate SET                
           
            status_courier ='21'          

            where  consolidate_id=:id      
        ");



    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_deleteConsolidatePackages($datos)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate_packages SET                
           
            status_courier ='21'          

            where  consolidate_id=:id      
        ");



    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_deleteItemConsolidate($datos)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_consolidate_detail WHERE order_id=:id');
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_updateItemConsolidate($datos)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_add_order SET                
           
            is_consolidate ='0'          

            where  order_id=:id      
        ");



    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_updateConsolidateDelete($id)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_add_order SET                
           
            is_consolidate ='0'          

            where  order_id=:id      
        ");



    $db->bind(':id', $id);

    return $db->cdp_execute();
}
function cdp_updateConsolidatePackagesDelete($id)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_customers_packages SET                
           
            is_consolidate ='0'          

            where  order_id=:id      
        ");



    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function cdp_getItemdeleteConsolidate($datos)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_consolidate_detail WHERE consolidate_id=:id');

    $db->bind(':id', $datos['id']);

    $db->cdp_execute();

    $data = $db->cdp_registros();

    return $data;
}


function cdp_refuesePickup($datos)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_add_order SET                
           
            status_courier ='12'          

            where  order_id=:id      
        ");



    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_checkDuplicateCourierTrack($tracking, $status)
{
    $db = new Conexion;

    $db->cdp_query("
        SELECT COUNT(*) AS count
        FROM cdb_courier_track
        WHERE order_track = :tracking
        AND status_courier = :status
    ");

    $db->bind(':tracking', $tracking);
    $db->bind(':status', $status);
    $db->cdp_execute();

    $result = $db->cdp_registro();
    $count = $result->count;

    return $count > 0;
}



function cdp_updateStatusCourierMultiple($order_no, $status)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_add_order SET                
           
            status_courier =:status_courier          

            where  order_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':status_courier', $status);

    return $db->cdp_execute();
}

function cdp_updateStatusConsolidateMultiple($order_no, $status)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate SET                
           
            status_courier =:status_courier          

            where  c_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':status_courier', $status);

    return $db->cdp_execute();
}


function cdp_updateStatusConsolidatePackagesMultiple($order_no, $status)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate_packages SET                
           
            status_courier =:status_courier          

            where  c_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':status_courier', $status);

    return $db->cdp_execute();
}


function cdp_updateShipTrackingMultiple($order_track, $status, $comments, $office, $user)
{

    $db = new Conexion;

    $date = date('Y-m-d');
    $time = date("H:i:s");
    $date = $date . ' ' . $time;

    $db->cdp_query("
                INSERT INTO cdb_courier_track 
                (
                    order_track,                    
                    comments,
                    t_date,
                    status_courier,
                    office_id,
                    user_id
                    )
                VALUES
                    (
                    :order_track,                    
                    :comments,
                    :t_date,
                    :status_courier,
                    :office,                   
                    :user_id
                    )
            ");



    $db->bind(':order_track',  $order_track);
    $db->bind(':comments', $comments);
    $db->bind(':t_date',  trim($date));
    $db->bind(':status_courier', $status);
    $db->bind(':office', $office);
    $db->bind(':user_id',  $user);

    $db->cdp_execute();
}

function cdp_updateConsolidateTrackingMultiple($order_track, $status, $comments, $office, $user)
{

    $db = new Conexion;

    $date = date('Y-m-d');
    $time = date("H:i:s");
    $date = $date . ' ' . $time;

    $db->cdp_query("
                INSERT INTO cdb_courier_track 
                (
                    order_track,                    
                    comments,
                    t_date,
                    status_courier,
                    office_id,
                    user_id
                    )
                VALUES
                    (
                    :order_track,                    
                    :comments,
                    :t_date,
                    :status_courier,
                    :office,                   
                    :user_id
                    )
            ");



    $db->bind(':order_track',  $order_track);
    $db->bind(':comments', $comments);
    $db->bind(':t_date',  trim($date));
    $db->bind(':status_courier', $status);
    $db->bind(':office', $office);
    $db->bind(':user_id',  $user);

    $db->cdp_execute();
}



function cdp_getCourierMultiple($order_no)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_add_order WHERE order_no=:order_no');

    $db->bind(':order_no', $order_no);

    $db->cdp_execute();

    $data = $db->cdp_registro();


    return $data;
}

function cdp_getConsolidateMultiple($order_no)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_consolidate WHERE c_no=:order_no');

    $db->bind(':order_no', $order_no);

    $db->cdp_execute();

    $data = $db->cdp_registro();


    return $data;
}


function cdp_insertCharges($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_charges_order
        (
            order_id,
            charge_date,
            total,
            payment_type,
            user_id,
            note
        )

        VALUES (
            :order_id,
            :charge_date,
            :total,
            :payment_type,
            :user_id,
            :notes
            
        )');


    $db->bind(':order_id', $datos['order_id']);
    $db->bind(':charge_date',  date("Y-m-d H:i:s"));
    $db->bind(':total', $datos['total']);
    $db->bind(':payment_type', $datos['payment_type']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':user_id',  $datos['user']);



    return $db->cdp_execute();
}

function cdp_deleteCharge($datos)
{

    $db = new Conexion;

    $db->cdp_query("DELETE FROM  cdb_charges_order   where  id_charge=:id      
        ");



    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_updateCharges($datos)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_charges_order SET                
           
            total =:total,         
            payment_type =:payment_type,         
            note =:notes      

            where  id_charge=:id      
        ");


    $db->bind(':total', $datos['total']);
    $db->bind(':payment_type', $datos['payment_type']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':id', $datos['charge_id']);


    return $db->cdp_execute();
}


function cdp_getChargePrint($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_charges_order WHERE id_charge=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_getUsersAdminEmployees()
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_users WHERE (userlevel=2 or userlevel=9)');

    $db->cdp_execute();

    $data = $db->cdp_registros();

    return $data;
}



function cdp_insertNotificationsUsers($notification_id, $user)
{
    $db = new Conexion;

    $db->cdp_query("
            INSERT INTO cdb_notifications_users 
            (
                user_id,
                notification_id                   
            )
            VALUES
                (
                :user_id,                    
                :notification_id                                      
                )
        ");



    $db->bind(':notification_id',  $notification_id);
    $db->bind(':user_id', $user);

    return $db->cdp_execute();
}



function cdp_updateNotificationRead($user, $notification_id)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_notifications_users SET                
           
            notification_read ='1'                    
            where  notification_id=:notification_id 
            and user_id = :user_id  
        ");


    $db->bind(':user_id', $user);
    $db->bind(':notification_id', $notification_id);


    return $db->cdp_execute();
}



function cdp_updateNotificationStatus($user, $notification_id)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_notifications_users SET                
           
            notification_status ='1'                    
            where  notification_id=:notification_id 
            and user_id = :user_id  
        ");


    $db->bind(':user_id', $user);
    $db->bind(':notification_id', $notification_id);


    return $db->cdp_execute();
}



function cdp_updateNotificatonsRea($user, $isAdmin = false)
{
    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_notifications_users SET notification_read = '1' WHERE user_id = :user_id");
    $db->bind(':user_id', (int)$user);
    $ok = $db->cdp_execute();

    if (!$ok) {
        return false;
    }

    if ($isAdmin) {
        // Also mark admin-visible system notifications as read.
        $db->cdp_query("
            UPDATE cdb_notifications_users nu
            INNER JOIN cdb_notifications n ON n.notification_id = nu.notification_id
            SET nu.notification_read = '1'
            WHERE nu.user_id = :user_id
              AND (n.user_id IS NULL OR n.user_id = 0)
        ");
        $db->bind(':user_id', (int)$user);
        if (!$db->cdp_execute()) {
            return false;
        }
    }

    return true;
}



function cdp_insertOrdersFiles($order_id, $target_file, $image_name, $date, $is_consolidate, $imageFileType)
{

    $db = new Conexion;

    $db->cdp_query("
            INSERT INTO cdb_order_files 
            (
                url,
                order_id,
                date_file,
                name,
                is_consolidate,
                file_type                   
            )
            VALUES
                (
                :url,
                :order_id,
                :date_file,
                :name,
                :is_consolidate,
                :file_type                                     
                )
        ");



    $db->bind(':order_id',  $order_id);
    $db->bind(':url', $target_file);
    $db->bind(':name', $image_name);
    $db->bind(':is_consolidate', $is_consolidate);
    $db->bind(':date_file', $date);
    $db->bind(':file_type', $imageFileType);

    return $db->cdp_execute();
}


function cdp_getCourierTrack($order_track)
{

    $db = new Conexion;


    $db->cdp_query("SELECT  a.photo_delivered, a.volumetric_percentage,  a.order_datetime, a.order_deli_time, a.status_invoice,  a.is_consolidate, a.is_pickup,  a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id, a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options,  b.mod_style, b.color FROM
             cdb_add_order as a
             INNER JOIN cdb_styles as b ON a.status_courier = b.id
             
             WHERE CONCAT(a.order_prefix,a.order_no)=:order_track
             
             ");

    $db->bind(':order_track', $order_track);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}




function cdp_updateDriverCourier($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_add_order SET    
                     
            driver_id =:driver_id              

            where  order_id=:id_shipment     
        ');


    $db->bind(':id_shipment', $datos['id_shipment']);
    $db->bind(':driver_id', $datos['driver_id']);

    return $db->cdp_execute();
}



function cdp_updateDriverConsolidate($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_consolidate SET    
                     
            driver_id =:driver_id              

            where  consolidate_id=:id_shipment     
        ');


    $db->bind(':id_shipment', $datos['id_shipment']);
    $db->bind(':driver_id', $datos['driver_id']);

    return $db->cdp_execute();
}



function cdp_updateDriverConsolidatePackages($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_consolidate_packages SET    
                     
            driver_id =:driver_id              

            where  consolidate_id=:id_shipment     
        ');


    $db->bind(':id_shipment', $datos['id_shipment']);
    $db->bind(':driver_id', $datos['driver_id']);

    return $db->cdp_execute();
}






// ===========================================================
// PRE ALERTS ADD
// ===========================================================

function cdp_insertPreAlert($datos)
{


    $db = new Conexion;

    $db->cdp_query("
            INSERT INTO cdb_pre_alert 
            (
                tracking,
                provider_shop,
                courier_com,
                customer_id,
                purchase_price,
                package_description,                 
                estimated_date,
                prealert_date,
                url_invoice                    
                )
            VALUES
                (
                :tracking,
                :provider_shop,
                :courier_com,
                :customer_id,
                :purchase_price,
                :package_description,                 
                :estimated_date,
                :prealert_date,
                :url_invoice
                )
        ");


    $db->bind(':tracking', $datos["tracking_prealert"]);
    $db->bind(':provider_shop', $datos["provider_prealert"]);
    $db->bind(':courier_com', $datos["courier_prealert"]);
    $db->bind(':customer_id',  $datos['customer_id']);
    $db->bind(':purchase_price', $datos["price_prealert"]);
    $db->bind(':package_description', $datos["description_prealert"]);
    $db->bind(':estimated_date', $datos['estimated_date']);
    $db->bind(':prealert_date',  $datos['prealert_date']);
    $db->bind(':url_invoice',  $datos['file_invoice']);




    return $db->cdp_execute();
}

function cdp_getCustomerPackagePrint($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_customers_packages WHERE order_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_updateStatusCustomerPackageMultiple($order_no, $status)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_customers_packages SET                
           
            status_courier =:status_courier          

            where  order_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':status_courier', $status);

    return $db->cdp_execute();
}



function cdp_getPackageMultiple($order_no)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_customers_packages WHERE order_no=:order_no');

    $db->bind(':order_no', $order_no);

    $db->cdp_execute();

    $data = $db->cdp_registro();


    return $data;
}



function cdp_updatePackageMultiple($order_track, $status, $comments, $office, $user)
{

    $db = new Conexion;

    $date = date('Y-m-d');
    $time = date("H:i:s");
    $date = $date . ' ' . $time;

    $db->cdp_query("
                INSERT INTO cdb_courier_track 
                (
                    order_track,                    
                    comments,
                    t_date,
                    status_courier,
                    office_id,
                    user_id
                    )
                VALUES
                    (
                    :order_track,                    
                    :comments,
                    :t_date,
                    :status_courier,
                    :office,                   
                    :user_id
                    )
            ");



    $db->bind(':order_track',  $order_track);
    $db->bind(':comments', $comments);
    $db->bind(':t_date',  trim($date));
    $db->bind(':status_courier', $status);
    $db->bind(':office', $office);
    $db->bind(':user_id',  $user);

    $db->cdp_execute();
}


function cdp_updateDriverPackage($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_customers_packages SET    
                     
            driver_id =:driver_id              

            where  order_id=:id_shipment     
        ');


    $db->bind(':id_shipment', $datos['id_shipment']);
    $db->bind(':driver_id', $datos['driver_id']);

    return $db->cdp_execute();
}







function cdp_updatePaymentPackagesCustomer($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_customers_packages SET    
                     
            notes =:notes,              
            status_invoice =:status_invoice,  
            url_payment_attach=:file_invoice,
            order_pay_mode=:mode_pay,
            payment_date=:payment_date          

            where  order_id=:order_id     
        ');


    $db->bind(':notes', $datos['notes']);
    $db->bind(':status_invoice', $datos['status_invoice']);
    $db->bind(':file_invoice', $datos['file_invoice']);
    $db->bind(':payment_date', $datos['payment_date']);
    $db->bind(':mode_pay', $datos['mode_pay']);
    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}



function cdp_updatePaymentCourierCustomer($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_add_order SET    
                     
            notes =:notes,              
            status_invoice =:status_invoice,  
            url_payment_attach=:file_invoice,
            order_pay_mode=:mode_pay,
            payment_date=:payment_date          

            where  order_id=:order_id     
        ');


    $db->bind(':notes', $datos['notes']);
    $db->bind(':status_invoice', $datos['status_invoice']);
    $db->bind(':file_invoice', $datos['file_invoice']);
    $db->bind(':payment_date', $datos['payment_date']);
    $db->bind(':mode_pay', $datos['mode_pay']);
    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}


function cdp_updatePaymentConsolidate($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_consolidate SET    
                     
            notes =:notes,              
            status_invoice =:status_invoice,  
            url_payment_attach=:file_invoice,
            order_pay_mode=:mode_pay,
            payment_date=:payment_date          

            where  consolidate_id=:order_id     
        ');


    $db->bind(':notes', $datos['notes']);
    $db->bind(':status_invoice', $datos['status_invoice']);
    $db->bind(':file_invoice', $datos['file_invoice']);
    $db->bind(':payment_date', $datos['payment_date']);
    $db->bind(':mode_pay', $datos['mode_pay']);
    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}

function cdp_updatePaymentConsolidatePackages($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_consolidate_packages SET    
                     
            notes =:notes,              
            status_invoice =:status_invoice,  
            url_payment_attach=:file_invoice,
            order_pay_mode=:mode_pay,
            payment_date=:payment_date          

            where  consolidate_id=:order_id     
        ');


    $db->bind(':notes', $datos['notes']);
    $db->bind(':status_invoice', $datos['status_invoice']);
    $db->bind(':file_invoice', $datos['file_invoice']);
    $db->bind(':payment_date', $datos['payment_date']);
    $db->bind(':mode_pay', $datos['mode_pay']);
    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}




function cdp_confirmPaymentPackages($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_customers_packages SET    
                           
            status_invoice =:status_invoice                    

            where  order_id=:order_id     
        ');


    $db->bind(':status_invoice', $datos['status_invoice']);

    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}


function cdp_confirmPaymentCourier($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_add_order SET    
                           
            status_invoice =:status_invoice                    

            where  order_id=:order_id     
        ');


    $db->bind(':status_invoice', $datos['status_invoice']);

    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}


function cdp_confirmPaymentConsolidate($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_consolidate SET    
                           
            status_invoice =:status_invoice                    

            where  consolidate_id=:order_id     
        ');


    $db->bind(':status_invoice', $datos['status_invoice']);

    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}


function cdp_confirmPaymentConsolidatePackages($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_consolidate_packages SET    
                           
            status_invoice =:status_invoice                    

            where  consolidate_id=:order_id     
        ');


    $db->bind(':status_invoice', $datos['status_invoice']);

    $db->bind(':order_id', $datos['order_id']);

    return $db->cdp_execute();
}


function cdp_updateDriverCourierMultiple($order_no, $driver)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_add_order SET                
           
            driver_id =:driver_id          

            where  order_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':driver_id', $driver);

    return $db->cdp_execute();
}



function cdp_updateDriverConsolidateMultiple($order_no, $driver)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate SET                
           
            driver_id =:driver_id          

            where  c_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':driver_id', $driver);

    return $db->cdp_execute();
}


function cdp_updateDriverConsolidatePackagesMultiple($order_no, $driver)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate_packages SET                
           
            driver_id =:driver_id          

            where  c_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':driver_id', $driver);

    return $db->cdp_execute();
}

function cdp_updateDriverCustomersPackageMultiple($order_no, $driver)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_customers_packages SET                
           
            driver_id =:driver_id          

            where  order_no=:id      
        ");



    $db->bind(':id', $order_no);
    $db->bind(':driver_id', $driver);

    return $db->cdp_execute();
}



function cdp_getPreAlert($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_pre_alert WHERE pre_alert_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_getCustomersPackagesTrack($order_track)
{

    $db = new Conexion;


    $db->cdp_query("SELECT  a.photo_delivered, a.volumetric_percentage,  a.order_datetime, a.order_deli_time, a.status_invoice, a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options,  b.mod_style, b.color FROM cdb_customers_packages as a
         INNER JOIN cdb_styles as b ON a.status_courier = b.id
         
         WHERE CONCAT(a.order_prefix,a.order_no)=:order_track
         
         ");

    $db->bind(':order_track', $order_track);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_insertPaymentGateway($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_payments_gateway
        (

            order_track,
            order_track_customer_id,
            gateway,
            payment_transaction,
            amount,
            status,
            type_transaccition_courier,
            currency,
            date_payment
        )

        VALUES (
            :order_track,
            :order_track_customer_id,
            :gateway,
            :payment_id,
            :amount,
            :status,
            :type_transaccition_courier,
            :currency_code,
            :date_payment
            
        )');




    $db->bind(':amount', $datos['amount']);
    $db->bind(':gateway', $datos['gateway']);
    $db->bind(':currency_code', $datos['currency_code']);
    $db->bind(':status', $datos['status']);
    $db->bind(':payment_id', $datos['payment_id']);
    $db->bind(':type_transaccition_courier', $datos['type_transaccition_courier']);
    $db->bind(':date_payment', $datos['date']);
    $db->bind(':order_track', $datos['order_track']);
    $db->bind(':order_track_customer_id', $datos['order_track_customer_id']);


    return $db->cdp_execute();
}



function cdp_updateTwilioConfig($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            twilio_token =:twilio_token,                
            twilio_sid =:twilio_sid,            
            twilio_number =:twilio_number                
            
        ');


    $db->bind(':twilio_token', $datos['twilio_token']);
    $db->bind(':twilio_sid', $datos['twilio_sid']);
    $db->bind(':twilio_number', $datos['twilio_number']);


    return $db->cdp_execute();
}


function cdp_updateTwiliosmsConfig($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            twilio_sms_token =:twilio_sms_token,                
            twilio_sms_sid =:twilio_sms_sid,            
            twilio_sms_number =:twilio_sms_number,
            active_sms =:active_sms               
            
        ');


    $db->bind(':twilio_sms_token', $datos['twilio_sms_token']);
    $db->bind(':twilio_sms_sid', $datos['twilio_sms_sid']);
    $db->bind(':twilio_sms_number', $datos['twilio_sms_number']);
    $db->bind(':active_sms', $datos['active_sms']);


    return $db->cdp_execute();
}



function cdp_updatePaymentConfig($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET


            public_key_paystack =:paystack_public_key,                
            secret_key_paystack =:paystack_secret_key,  
            public_key_stripe =:stripe_public_key,                
            secret_key_stripe =:stripe_secret_key,
            paypal_client_id =:client_id,
            active_paystack =:active_paystack,                
            active_stripe =:active_stripe,            
            active_attach_proof =:active_attach_proof,              
            active_paypal =:active_paypal               
            
        ');


    $db->bind(':paystack_public_key', $datos['paystack_public_key']);
    $db->bind(':paystack_secret_key', $datos['paystack_secret_key']);
    $db->bind(':stripe_public_key', $datos['stripe_public_key']);
    $db->bind(':stripe_secret_key', $datos['stripe_secret_key']);
    $db->bind(':client_id', $datos['client_id']);
    $db->bind(':active_paystack', $datos['active_paystack']);
    $db->bind(':active_stripe', $datos['active_stripe']);
    $db->bind(':active_attach_proof', $datos['active_attach_proof']);
    $db->bind(':active_paypal', $datos['active_paypal']);


    return $db->cdp_execute();
}



function cdp_updateTwilioWhatssapConfig($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            active_whatsapp =:active_whatsapp              
            
        ');


    $db->bind(':active_whatsapp', $datos['active_whatsapp']);


    return $db->cdp_execute();
}


function cdp_updateTwiliosmsactiveConfig($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            active_sms =:active_sms              
            
        ');


    $db->bind(':active_sms', $datos['active_sms']);


    return $db->cdp_execute();
}


function cdp_getAddressesUsers($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_users_multiple_addresses WHERE user_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registros();

    return $data;
}


function cdp_deleteAddressesUsers($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_users_multiple_addresses WHERE user_id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}



function cdp_deleteFileCourier($datos)
{

    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_order_files WHERE id=:id');

    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_deleteFileCustomerPackages($datos)
{

    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_customer_package_files WHERE id=:id');

    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_insertDriverFiles($driver_id, $target_file, $image_name, $date, $imageFileType)
{

    $db = new Conexion;

    $db->cdp_query("
            INSERT INTO cdb_driver_files 
            (
                url,
                driver_id,
                date_file,
                name,
                file_type                   
            )
            VALUES
                (
                :url,
                :driver_id,
                :date_file,
                :name,
                :file_type                                     
                )
        ");



    $db->bind(':driver_id',  $driver_id);
    $db->bind(':url', $target_file);
    $db->bind(':name', $image_name);
    $db->bind(':date_file', $date);
    $db->bind(':file_type', $imageFileType);

    return $db->cdp_execute();
}



function cdp_insertCustomerPackagesFiles($order_id, $target_file, $image_name, $date, $imageFileType)
{

    $db = new Conexion;

    $db->cdp_query("
            INSERT INTO cdb_customer_package_files 
            (
                url,
                order_id,
                date_file,
                name,
                file_type                   
            )
            VALUES
                (
                :url,
                :order_id,
                :date_file,
                :name,
                :file_type                                     
                )
        ");



    $db->bind(':order_id',  $order_id);
    $db->bind(':url', $target_file);
    $db->bind(':name', $image_name);
    $db->bind(':date_file', $date);
    $db->bind(':file_type', $imageFileType);

    return $db->cdp_execute();
}




function cdp_deleteFileDrivers($datos)
{

    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_driver_files WHERE id=:id');

    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


// ===========================================================
// CRUD COUNTRIES
// ===========================================================

function cdp_countryExists($name, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_countries WHERE  name=:name $where");
    $db->bind(':name', $name);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertCountry($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_countries
        (
            name,
            iso3,
            phone_code,
            capital,
            currency_name,
            currency_symbol,
            region,
            is_active
            
        )

        VALUES (

            :name,
            :iso3,
            :phone_code,
            :capital,
            :currency_name,
            :currency_symbol,
            :region,
            :is_active
            
        )');



    $db->bind(':name', $datos['name']);
    $db->bind(':iso3', $datos['iso3']);
    $db->bind(':phone_code', $datos['phone_code']);
    $db->bind(':capital', $datos['capital']);
    $db->bind(':currency_name', $datos['currency_name']);
    $db->bind(':currency_symbol', $datos['currency_symbol']);
    $db->bind(':region', $datos['region']);
    $db->bind(':is_active', $datos['is_active']);



    return $db->cdp_execute();
}


function cdp_getCountry($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_countries WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}

/**
 * Lista todos los países para import/referencia.
 * @return array [{id, name}, ...]
 */
function cdp_getAllCountries()
{
    $db = new Conexion;
    $db->cdp_query('SELECT id, name FROM cdb_countries ORDER BY name');
    $db->cdp_execute();
    $rows = $db->cdp_registros();
    return is_array($rows) ? $rows : [];
}



function cdp_updateCountry($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_countries SET    
            name=:name,
            iso3=:iso3,
            phone_code=:phone_code,
            capital=:capital,
            currency_name=:currency_name,
            currency_symbol=:currency_symbol,
            is_active=:is_active,
            region=:region
            where  id=:id      
        ');


    $db->bind(':name', $datos['name']);
    $db->bind(':iso3', $datos['iso3']);
    $db->bind(':region', $datos['region']);
    $db->bind(':capital', $datos['capital']);
    $db->bind(':currency_name', $datos['currency_name']);
    $db->bind(':currency_symbol', $datos['currency_symbol']);
    $db->bind(':phone_code', $datos['phone_code']);
    $db->bind(':is_active', $datos['is_active']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}

function cdp_verifyReferentialIntegrityCounty($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_states WHERE country_id=:id');
    $db->bind(':id', $id);

    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result > 0) {
        return true;
    } else {
        return false;
    }
}

function cdp_deleteCounty($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_countries WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


// ===========================================================
// CRUD STATES
// ===========================================================

function cdp_stateExists($name, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_states WHERE  name=:name $where");
    $db->bind(':name', $name);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertState($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_states
        (
            name,
            country_id,
            iso
        )

        VALUES (

            :state_name,
            :country,
            :iso
        )');



    $db->bind(':state_name', $datos['state_name']);
    $db->bind(':iso', $datos['iso']);
    $db->bind(':country', $datos['country']);

    return $db->cdp_execute();
}


function cdp_getState($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_states WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_updateState($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_states SET    
            name=:state_name,
            country_id=:country,
            iso=:iso

            where  id=:id      
        ');


    $db->bind(':id', $datos['id']);
    $db->bind(':state_name', $datos['state_name']);
    $db->bind(':iso', $datos['iso']);
    $db->bind(':country', $datos['country']);

    return $db->cdp_execute();
}

function cdp_verifyReferentialIntegrityState($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_cities WHERE state_id=:id');
    $db->bind(':id', $id);

    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result > 0) {
        return true;
    } else {
        return false;
    }
}

function cdp_deleteState($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_states WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


// ===========================================================
// CRUD CITIES
// ===========================================================

function cdp_cityExists($name, $id = null)
{

    $db = new Conexion;

    $where = '';
    if ($id != null) {

        $where = "and id!='$id'";
    }

    $db->cdp_query("SELECT * FROM cdb_cities WHERE  name=:name $where");
    $db->bind(':name', $name);
    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result == 1) {

        return true;
    } else {

        return false;
    }
}


function cdp_insertCity($datos)
{

    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_cities
        (
            name,
            state_id        
        )

        VALUES (
            :city_name,
            :state            
        )');

    $db->bind(':city_name', $datos['city_name']);
    $db->bind(':state', $datos['state']);

    return $db->cdp_execute();
}


function cdp_getCity($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_cities WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}



function cdp_updateCity($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_cities SET    
            name=:city_name,
            state_id=:state

            where  id=:id      
        ');

    $db->bind(':id', $datos['id']);
    $db->bind(':city_name', $datos['city_name']);
    $db->bind(':state', $datos['state']);

    return $db->cdp_execute();
}



function cdp_deleteCity($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_cities WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}



// ===========================================================
// CRUD RECIPIENTS
// ===========================================================


function cdp_insertRecipient($datos)
{

    $db = new Conexion;

    $has_agency = isset($datos['agency_id']) && (int)$datos['agency_id'] > 0;
    $db->cdp_query('INSERT INTO cdb_recipients
              (   
                  email,
                  fname,
                  lname,
                  phone,
                  sender_id' . ($has_agency ? ', agency_id' : '') . '
              )

              VALUES (

                  :email,
                  :fname,
                  :lname,           
                  :phone,
                  :sender_id' . ($has_agency ? ', :agency_id' : '') . '
                  
              )');

    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':sender_id', $datos['sender_id']);
    if ($has_agency) {
        $db->bind(':agency_id', (int)$datos['agency_id']);
    }

    $db->cdp_execute();

    return $db->dbh->lastInsertId();
}

function cdp_insertAddressRecipient($datos)
{

    $db = new Conexion;

    $db->cdp_query("
        INSERT INTO cdb_recipients_addresses 
        (
            country,
            state,
            city,
            zip_code,
            address,
            recipient_id                                
        )
        VALUES 
        (
            :country,
            :state,
            :city, 
            :zip_code,
            :address,
            :recipient_id                            
        )
    ");

    $db->bind(':recipient_id',   $datos['recipient_id']);
    $db->bind(':address',  $datos["address"]);
    $db->bind(':country',  $datos["country"]);
    $db->bind(':city',  $datos["city"]);
    $db->bind(':state',  $datos["state"]);
    $db->bind(':zip_code',  $datos["postal"]);

    $result = $db->cdp_execute();
    $GLOBALS['cdp_error'] = $db->cdp_error();
    return  $result;
}

function cdp_insertTariffsCustom($datos)
{
    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_shipping_fees
    (
        origin,
        destiny,
        state,
        city,
        order_item_category,
        order_package,
        order_courier,
        order_service_options,
        initial_range,
        final_range,
        price
    )
    VALUES (
        :country_origin,
        :country_destiny,
        :state_destinystates,
        :city_destinycities,
        :order_item_category,
        :order_package,
        :order_courier,
        :ship_mode,
        :initial_range,
        :final_range,
        :tariff_price
    )');

    // obligatorios del form
    $db->bind(':country_origin',      $datos['country_origin']);
    $db->bind(':country_destiny',     $datos['country_destiny']);
    $db->bind(':state_destinystates', $datos['state_destinystates']);
    $db->bind(':city_destinycities',  $datos['city_destinycities']);
    $db->bind(':ship_mode',           $datos['ship_mode']);
    $db->bind(':initial_range',       $datos['initial_range']);
    $db->bind(':final_range',         $datos['final_range']);
    $db->bind(':tariff_price',        $datos['tariff_price']);

    // columnas presentes en la tabla pero no en el form -> por defecto 0
    $db->bind(':order_item_category', isset($datos['order_item_category']) ? (int)$datos['order_item_category'] : 0);
    $db->bind(':order_package',       isset($datos['order_package']) ? (int)$datos['order_package'] : 0);
    $db->bind(':order_courier',       isset($datos['order_courier']) ? (int)$datos['order_courier'] : 0);

    return $db->cdp_execute();
}



function cdp_updateRecipientAddress($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_recipients_addresses SET
    
        country =:country,
        state =:state,
        city =:city,
        zip_code =:zip_code,
        address =:address
  
        where id_addresses = :id_addresses
    ');

    $db->bind(':id_addresses',   $datos['address_id']);
    $db->bind(':address',  $datos["address"]);
    $db->bind(':country',  $datos["country"]);
    $db->bind(':city',  $datos["city"]);
    $db->bind(':state',  $datos["state"]);
    $db->bind(':zip_code',  $datos["postal"]);

    return $db->cdp_execute();
}

function cdp_deleteRecipientAddress($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_recipients_addresses WHERE id_addresses=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function cdp_updateRecipient($datos)
{
    $db = new Conexion;

    $db->cdp_query("
    UPDATE cdb_recipients SET                
        
      fname=:fname,
      lname=:lname,           
      phone=:phone,
      email=:email                  

    WHERE id=:id_recipient                
    
  ");

    $db->bind(':id_recipient',   $datos['id_recipient']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':phone', $datos['phone']);

    return $db->cdp_execute();
}


function cdp_getRecipientEdit($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_recipients WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}

function cdp_deleteRecipient($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_recipients WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function cdp_recipientEmailExiste($email)
{
    $db = new Conexion;
    $db->cdp_query("SELECT email FROM cdb_recipients WHERE email = :email");
    $db->bind(':email', $email);
    $db->cdp_execute();

    return  $db->cdp_registro();
}


// ===========================================================
// USERS CUSTOMERS
// ===========================================================



function cdp_insertCustomer($datos)
{

    $db = new Conexion;

    $has_name_off = isset($datos['name_off']) && $datos['name_off'] !== '';
    $has_agency_id = isset($datos['agency_id']) && (int)$datos['agency_id'] > 0;
    $db->cdp_query('INSERT INTO cdb_users
    (
        username,
        password,
        locker,
        userlevel,
        email,
        document_type,
        document_number,
        fname,
        lname,
        created,
        notes,
        phone,
        gender,
        newsletter,
        active' . ($has_name_off ? ', name_off' : '') . ($has_agency_id ? ', agency_id' : '') . '
    )
    VALUES (
        :username,
        :password,
        :locker,
        :userlevel,
        :email,
        :document_type,
        :document_number,
        :fname,
        :lname,
        :created,
        :notes,
        :phone,
        :gender,
        :newsletter,
        :active' . ($has_name_off ? ', :name_off' : '') . ($has_agency_id ? ', :agency_id' : '') . '
    )');


    $db->bind(':username', $datos['username']);
    $db->bind(':locker', $datos['locker']);
    $db->bind(':password', $datos['password']);
    $db->bind(':userlevel', $datos['userlevel']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':created', $datos['created']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':gender', $datos['gender']);
    $db->bind(':newsletter', $datos['newsletter']);
    $db->bind(':active', $datos['active']);
    $db->bind(':document_type', $datos['document_type']);
    $db->bind(':document_number', $datos['document_number']);
    if ($has_name_off) {
        $db->bind(':name_off', $datos['name_off']);
    }
    if ($has_agency_id) {
        $db->bind(':agency_id', (int)$datos['agency_id']);
    }

    $db->cdp_execute();
    return $db->dbh->lastInsertId();
}

function cdp_updateCustomers($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_users SET
    
        password =:password,
        email =:email,
        document_type =:document_type,
        document_number =:document_number,
        fname =:fname,
        lname =:lname,
        notes =:notes,
        phone =:phone,
        gender =:gender,
        newsletter =:newsletter,
        active =:active

        where id = :id
    ');

    $db->bind(':password', $datos['password']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':gender', $datos['gender']);
    $db->bind(':newsletter', $datos['newsletter']);
    $db->bind(':active', $datos['active']);
    $db->bind(':document_type', $datos['document_type']);
    $db->bind(':document_number', $datos['document_number']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_updateCustomersprofile($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_users SET
    
        password =:password,
        email =:email,
        document_type =:document_type,
        document_number =:document_number,
        fname =:fname,
        lname =:lname,
        notes =:notes,
        phone =:phone,
        gender =:gender

        where id = :id
    ');

    $db->bind(':password', $datos['password']);
    $db->bind(':email', $datos['email']);
    $db->bind(':fname', $datos['fname']);
    $db->bind(':lname', $datos['lname']);
    $db->bind(':notes', $datos['notes']);
    $db->bind(':phone', $datos['phone']);
    $db->bind(':gender', $datos['gender']);
    $db->bind(':document_type', $datos['document_type']);
    $db->bind(':document_number', $datos['document_number']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}



function cdp_insertAddressCustomer($datos)
{

    $db = new Conexion;

    $db->cdp_query("
        INSERT INTO cdb_senders_addresses 
        (
            country,
            state,
            city,
            zip_code,
            address,
            user_id                                
        )
        VALUES 
        (
            :country,
            :state,
            :city, 
            :zip_code,
            :address,
            :user_id                            
        )
    ");

    $db->bind(':user_id',   $datos['user_id']);
    $db->bind(':address',  $datos["address"]);
    $db->bind(':country',  $datos["country"]);
    $db->bind(':city',  $datos["city"]);
    $db->bind(':state',  $datos["state"]);
    $db->bind(':zip_code',  $datos["postal"]);

    return  $db->cdp_execute();
}


function cdp_insertAddressLocker($datos)
{

    $db = new Conexion;

    $db->cdp_query("
        INSERT INTO cdb_virtual_locker 
        (
            prefixlocker,
            digitslockers,
            created,
            active,
            user_id                                
        )
        VALUES 
        (
            :prefixlocker,                    
            :digitslockers,                    
            :created,
            :active, 
            :user_id                            
        )
    ");

    $db->bind(':user_id',   $datos['user_id']);
    $db->bind(':prefixlocker',  $datos["prefixlocker"]);
    $db->bind(':digitslockers',  $datos["digitslockers"]);
    $db->bind(':created',  $datos["created"]);
    $db->bind(':active',  $datos["active"]);


    return  $db->cdp_execute();
}

function cdp_updateCustomerAddress($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_senders_addresses SET
    
        country =:country,
        state =:state,
        city =:city,
        zip_code =:zip_code,
        address =:address
  
        where id_addresses = :id_addresses
    ');

    $db->bind(':id_addresses',   $datos['address_id']);
    $db->bind(':address',  $datos["address"]);
    $db->bind(':country',  $datos["country"]);
    $db->bind(':city',  $datos["city"]);
    $db->bind(':state',  $datos["state"]);
    $db->bind(':zip_code',  $datos["postal"]);

    return $db->cdp_execute();
}

function cdp_deleteCustomerAddress($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_senders_addresses WHERE id_addresses=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function cdp_verifyReferentialIntegrity($table, $field, $id)
{
    $db = new Conexion;
    $db->cdp_query("SELECT * FROM  $table WHERE $field = :id");
    $db->bind(':id', $id);

    $db->cdp_execute();
    $result = $db->cdp_rowCount();

    if ($result > 0) {
        return true;
    } else {
        return false;
    }
}



// ===========================================================
//  tariffs
// ===========================================================
function cdp_insertTariffs($datos)
{
    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_shipping_fees
    (
        client_id,
        origin,
        destiny,
        state,
        city,
        order_item_category,
        order_package,
        order_courier,
        order_service_options,
        initial_range,
        final_range,
        price,
        volumetric_percentage,
        price_mile
    )
    VALUES (
        :client_id,
        :country_origin,
        :country_destiny,
        :state_destinystates,
        :city_destinycities,
        :order_item_category,
        :order_package,
        :order_courier,
        :ship_mode,
        :initial_range,
        :final_range,
        :tariff_price,
        :volumetric_percentage,
        :price_mile
    )');

    // Client (opcional)
    $clientId = (isset($datos['client_id']) && $datos['client_id'] !== '') ? (int)$datos['client_id'] : null;
    $db->bind(':client_id', $clientId);

    // Campos del form
    $db->bind(':country_origin',      $datos['country_origin']);
    $db->bind(':country_destiny',     $datos['country_destiny']);
    $db->bind(':state_destinystates', $datos['state_destinystates']);
    $db->bind(':city_destinycities',  $datos['city_destinycities']);
    $db->bind(':ship_mode',           $datos['ship_mode']);     // mapea a order_service_options
    $db->bind(':initial_range',       $datos['initial_range']);
    $db->bind(':final_range',         $datos['final_range']);
    $db->bind(':tariff_price',        $datos['tariff_price']);  // mapea a price
    $db->bind(':volumetric_percentage', isset($datos['volumetric_percentage']) ? (float)$datos['volumetric_percentage'] : 5000);
    $db->bind(':price_mile',         isset($datos['price_mile']) ? (float)$datos['price_mile'] : 0);

    // Estas 3 existen en la tabla pero no vienen del form, se dejan en 0 por ahora
    $db->bind(':order_item_category', isset($datos['order_item_category']) ? (int)$datos['order_item_category'] : 0);
    $db->bind(':order_package',       isset($datos['order_package']) ? (int)$datos['order_package'] : 0);
    $db->bind(':order_courier',       isset($datos['order_courier']) ? (int)$datos['order_courier'] : 0);

    return $db->cdp_execute();
}



/**
 * Verifica si existe otra tarifa con la misma ruta (origin,destiny,state,city,order_service_options,client_id)
 * y rango de peso solapado. Si $id se pasa, se excluye ese registro (para edición).
 * @param int $origin
 * @param int $destiny
 * @param float $initial_range
 * @param float $final_range
 * @param int|null $id Id a excluir (edición)
 * @param int|null $state
 * @param int|null $city
 * @param int|null $order_service_options
 * @param int|null $client_id
 * @return bool true si hay solapamiento
 */
function cdp_verifyRangeTariffsExist($origin, $destiny, $initial_range, $final_range, $id = null, $state = null, $city = null, $order_service_options = null, $client_id = null)
{
    $db = new Conexion;
    $initial_range = floatval($initial_range);
    $final_range = floatval($final_range);
    $where = ' AND (COALESCE(a.state,0) = COALESCE(:state,0)) AND (COALESCE(a.city,0) = COALESCE(:city,0))'
        . ' AND (COALESCE(a.order_service_options,0) = COALESCE(:order_service_options,0))'
        . ' AND (COALESCE(a.client_id,0) = COALESCE(:client_id,0))'
        . ' AND (a.initial_range <= :final_range AND a.final_range >= :initial_range2) ';
    // Al editar, excluir el propio registro (id > 0). Si id es null o 0, no excluir (alta nueva).
    if ($id !== null && $id !== '' && (int)$id > 0) {
        $where .= " AND a.id != :id";
    }

    $sql = "SELECT COUNT(*) as total FROM cdb_shipping_fees a
            WHERE a.origin = :origin AND a.destiny = :destiny
            $where";

    $db->cdp_query($sql);
    $db->bind(':origin', (int)$origin);
    $db->bind(':destiny', (int)$destiny);
    $db->bind(':state', $state === null || $state === '' ? null : (int)$state);
    $db->bind(':city', $city === null || $city === '' ? null : (int)$city);
    $db->bind(':order_service_options', $order_service_options === null || $order_service_options === '' ? null : (int)$order_service_options);
    $db->bind(':client_id', $client_id === null || $client_id === '' ? null : (int)$client_id);
    $db->bind(':initial_range2', $initial_range);
    $db->bind(':final_range', $final_range);
    if ($id !== null && $id !== '' && (int)$id > 0) {
        $db->bind(':id', (int)$id);
    }

    $db->cdp_execute();
    $result = $db->cdp_registro();

    return $result && (int)$result->total > 0;
}




function cdp_getTariffsEdit($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_shipping_fees WHERE id=:id');
    $db->bind(':id', $id);
    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}

function cdp_updateTariffs($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_shipping_fees SET
    
        origin =:country_origin,
        destiny =:country_destiny,
        state =:state_destinystates,
        city =:city_destinycities,
        order_service_options =:ship_mode,
        initial_range =:initial_range,
        final_range =:final_range,
        price =:tariff_price,
        price_mile =:price_mile,
        volumetric_percentage=:volumetric_percentage,
        client_id = :client_id

        where id = :id
    ');

    $db->bind(':country_origin', $datos['country_origin']);
    $db->bind(':country_destiny', $datos['country_destiny']);
    $db->bind(':state_destinystates', $datos['state_destinystates']);
    $db->bind(':city_destinycities', $datos['city_destinycities']);
    $db->bind(':ship_mode', $datos['ship_mode']);
    $db->bind(':initial_range', $datos['initial_range']);
    $db->bind(':final_range', $datos['final_range']);
    $db->bind(':tariff_price', $datos['tariff_price']);
    $db->bind(':price_mile', $datos['price_mile']);
    $db->bind(':volumetric_percentage', $datos['volumetric_percentage']);
    $db->bind(':client_id', $datos['client_id']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}



function cdp_deleteTariffs($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_shipping_fees WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}

/**
 * Calcula tarifa por rango de peso server-side (misma lógica que get_price_range_weight_tariffs_ajax).
 * Usar en add_courier_ajax / edit_courier_ajax cuando manual_tariff = 0.
 * @param int $sender_id
 * @param int $sender_address_id
 * @param int $recipient_id
 * @param int $recipient_address_id
 * @param int|null $order_service_options
 * @param array $packages Array de objetos o arrays con qty, weight, length, width, height
 * @param float $distance_miles
 * @param float $meter Factor volumétrico (ej. de cdp_getSettingsCourier)
 * @return array|null ['chargeable_weight'=>float,'price_base'=>float,'cargo_millas'=>float,'total_tarifa'=>float,'tariff_id'=>int,'price_lb_derived'=>float] o null si no hay tarifa/datos
 */
function cdp_calculateTariffServerSide($sender_id, $sender_address_id, $recipient_id, $recipient_address_id, $order_service_options, $packages, $distance_miles, $meter)
{
    if (!$sender_id || !$sender_address_id || !$recipient_id || !$recipient_address_id || !$packages) {
        return null;
    }
    $meter = (float)$meter;

    $sender_address    = cdp_getSenderAddress($sender_address_id);
    $recipient_address = cdp_getRecipientAddress($recipient_address_id);
    if (!$sender_address || !$recipient_address) {
        return null;
    }

    $client_id = (int)$sender_id;
    $origin    = (int)$sender_address->country;
    $destiny   = (int)$recipient_address->country;
    $state     = (int)$recipient_address->state;
    $city      = (int)$recipient_address->city;
    $order_svc = ($order_service_options !== null && $order_service_options !== '') ? (int)$order_service_options : 0;
    $distance_miles = (float)$distance_miles;

    $is_air = false;
    if ($order_svc > 0) {
        $dbm = new Conexion;
        $dbm->cdp_query("SELECT name_item FROM cdb_category WHERE id = :id LIMIT 1");
        $dbm->bind(':id', $order_svc);
        $dbm->cdp_execute();
        $catRow = $dbm->cdp_registro();
        if ($catRow && !empty($catRow->name_item)) {
            $sm = mb_strtolower($catRow->name_item, 'UTF-8');
            if (strpos($sm, 'aereo') !== false || strpos($sm, 'aéreo') !== false || strpos($sm, 'air') !== false) {
                $is_air = true;
            }
        }
    }

    $peso_real_total  = 0.0;
    $volumetric_total = 0.0;
    foreach ($packages as $p) {
        $qty    = isset($p['qty'])    ? (float)$p['qty']    : (isset($p->qty)    ? (float)$p->qty    : 1.0);
        $length = isset($p['length']) ? (float)$p['length'] : (isset($p->length) ? (float)$p->length : 0.0);
        $width  = isset($p['width'])  ? (float)$p['width']  : (isset($p->width)  ? (float)$p->width  : 0.0);
        $height = isset($p['height']) ? (float)$p['height'] : (isset($p->height) ? (float)$p->height : 0.0);
        $weight = isset($p['weight']) ? (float)$p['weight'] : (isset($p->weight) ? (float)$p->weight : 0.0);
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $vw = ($meter > 0) ? (($length * $width * $height) / $meter) : 0.0;
        $peso_real_total  += $weight * $qty;
        $volumetric_total += $vw * $qty;
    }
    $peso_real_total   = round($peso_real_total, 2);
    $volumetric_total  = round($volumetric_total, 2);
    $chargeable_weight = $is_air ? max($peso_real_total, $volumetric_total) : $peso_real_total;
    $chargeable_weight = round($chargeable_weight, 2);

    if ($chargeable_weight <= 0) {
        return null;
    }

    $order_svc_ids = [$order_svc];
    if ($order_svc > 0) {
        $dbm = new Conexion;
        $dbm->cdp_query("SELECT name_item FROM cdb_category WHERE id = :id LIMIT 1");
        $dbm->bind(':id', $order_svc);
        $dbm->cdp_execute();
        $catRow = $dbm->cdp_registro();
        if ($catRow && !empty($catRow->name_item)) {
            $name = mb_strtolower(trim($catRow->name_item), 'UTF-8');
            $term = '%' . $name . '%';
            $dbm->cdp_query("SELECT id FROM cdb_shipping_mode WHERE LOWER(TRIM(COALESCE(ship_mode,''))) LIKE :term");
            $dbm->bind(':term', $term);
            $dbm->cdp_execute();
            $rows = $dbm->cdp_registros();
            if ($rows) {
                foreach ($rows as $r) {
                    $id = (int)$r->id;
                    if ($id > 0 && !in_array($id, $order_svc_ids, true)) {
                        $order_svc_ids[] = $id;
                    }
                }
            }
        }
    }
    $order_svc_ids[] = 0;

    $db = new Conexion;
    $placeholders = array_map(function ($i) { return ':order_svc_' . $i; }, array_keys($order_svc_ids));
    $order_svc_in = implode(', ', $placeholders);
    $sql = "
        SELECT *
        FROM cdb_shipping_fees
        WHERE
            (client_id = :cid OR client_id = 0 OR client_id IS NULL)
            AND origin = :origin AND destiny = :destiny
            AND (state IS NULL OR state = 0 OR state = :state)
            AND (city IS NULL OR city = 0 OR city = :city)
            AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
            AND :cw BETWEEN initial_range AND final_range
        ORDER BY (CASE WHEN client_id = :cid THEN 0 ELSE 1 END), (CASE WHEN order_service_options = :order_svc_exact THEN 0 ELSE 1 END), id DESC
        LIMIT 1
    ";
    $db->cdp_query($sql);
    $db->bind(':cid', $client_id);
    $db->bind(':origin', $origin);
    $db->bind(':destiny', $destiny);
    $db->bind(':state', $state);
    $db->bind(':city', $city);
    $db->bind(':order_svc_exact', $order_svc);
    foreach (array_combine($placeholders, $order_svc_ids) as $k => $v) {
        $db->bind($k, $v);
    }
    $db->bind(':cw', $chargeable_weight);
    $db->cdp_execute();
    $tariff = $db->cdp_registro();

    if (!$tariff) {
        $sql2 = "
            SELECT *
            FROM cdb_shipping_fees
            WHERE
                (client_id = :cid2 OR client_id = 0 OR client_id IS NULL)
                AND origin = :origin2 AND destiny = :destiny2
                AND (state IS NULL OR state = 0)
                AND (city IS NULL OR city = 0)
                AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
                AND :cw2 BETWEEN initial_range AND final_range
            ORDER BY (CASE WHEN client_id = :cid2 THEN 0 ELSE 1 END), (CASE WHEN order_service_options = :order_svc_exact2 THEN 0 ELSE 1 END), id DESC
            LIMIT 1
        ";
        $db->cdp_query($sql2);
        $db->bind(':cid2', $client_id);
        $db->bind(':origin2', $origin);
        $db->bind(':destiny2', $destiny);
        $db->bind(':order_svc_exact2', $order_svc);
        foreach (array_combine($placeholders, $order_svc_ids) as $k => $v) {
            $db->bind($k, $v);
        }
        $db->bind(':cw2', $chargeable_weight);
        $db->cdp_execute();
        $tariff = $db->cdp_registro();
    }
    if (!$tariff) {
        $sql3 = "
            SELECT *
            FROM cdb_shipping_fees
            WHERE
                (client_id = :cid3 OR client_id = 0 OR client_id IS NULL)
                AND origin = :origin3 AND destiny = :destiny3
                AND :cw3 BETWEEN initial_range AND final_range
            ORDER BY (CASE WHEN client_id = :cid3 THEN 0 ELSE 1 END), (CASE WHEN order_service_options = :order_svc_exact3 THEN 0 ELSE 1 END), id DESC
            LIMIT 1
        ";
        $db->cdp_query($sql3);
        $db->bind(':cid3', $client_id);
        $db->bind(':origin3', $origin);
        $db->bind(':destiny3', $destiny);
        $db->bind(':order_svc_exact3', $order_svc);
        $db->bind(':cw3', $chargeable_weight);
        $db->cdp_execute();
        $tariff = $db->cdp_registro();
    }
    if (!$tariff) {
        return null;
    }

    $flat_price   = round((float)$tariff->price, 2);
    $price_mile   = (float)($tariff->price_mile ?? 0);
    $cargo_millas = ($price_mile > 0 && $distance_miles > 0) ? round($price_mile * $distance_miles, 2) : 0.0;
    $total_tarifa = round($flat_price + $cargo_millas, 2);
    $price_lb_derived = $chargeable_weight > 0 ? round($total_tarifa / $chargeable_weight, 4) : $total_tarifa;

    return [
        'chargeable_weight' => $chargeable_weight,
        'price_base'        => $flat_price,
        'cargo_millas'      => $cargo_millas,
        'total_tarifa'      => $total_tarifa,
        'tariff_id'         => (int)$tariff->id,
        'price_lb_derived'  => $price_lb_derived,
    ];
}


// ===========================================================
//  COURIER SHIPMENTS
// ===========================================================

function cdp_getCourier($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_add_order WHERE order_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}
function cdp_deleteCourierAddress($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_address_shipments WHERE order_track=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}

function cdp_deleteCourierPackages($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_add_order_item WHERE order_id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}

function cdp_insertCourierShipment($datos)
{
    $db = new Conexion;

    $db->cdp_query("
    INSERT INTO cdb_add_order 
    (
        user_id,
        order_prefix,
        order_no,
        order_date,
        sender_id,
        sender_address_id,
        receiver_id,                
        receiver_address_id,
        volumetric_percentage,
        order_datetime,
        agency,
        origin_off,
        order_item_category, 
        order_package,
        order_courier,
        order_service_options,
        order_deli_time,                   
        order_payment_method,
        status_courier,
        driver_id,
        is_pickup,
        due_date,
        status_invoice,
        order_incomplete,
        manual_tariff,
        recipient_type           
        )
    VALUES
        (
        :user_id,
        :order_prefix,
        :order_no,
        :order_date,
        :sender_id,
        :sender_address_id,
        :receiver_id,       
        :receiver_address_id,    
        :volumetric_percentage,
        :order_datetime,
        :agency,
        :origin_off,
        :order_item_category, 
        :order_package,
        :order_courier,
        :order_service_options,
        :order_deli_time,                   
        :order_payment_method,
        :status_courier,
        :driver_id,
        :is_pickup,
        :due_date,
        :status_invoice,
        :order_incomplete,
        :manual_tariff,
        :recipient_type
        )
");

    $db->bind(':manual_tariff',  $datos['manual_tariff']);
    $db->bind(':order_incomplete',  $datos['order_incomplete']);
    $db->bind(':recipient_type',  $datos['recipient_type']);
    $db->bind(':is_pickup',  $datos['is_pickup']);
    $db->bind(':user_id',  $datos['user_id']);
    $db->bind(':order_prefix',  $datos['order_prefix']);
    $db->bind(':order_no', $datos["order_no"]);
    $db->bind(':order_datetime',  $datos['order_datetime']);
    $db->bind(':sender_id',  $datos["sender_id"]);
    $db->bind(':receiver_id',  $datos["recipient_id"]);
    $db->bind(':sender_address_id',  $datos["sender_address_id"]);
    $db->bind(':receiver_address_id',  $datos["recipient_address_id"]);
    $db->bind(':order_date',  $datos['order_date']);
    $db->bind(':agency',  $datos["agency"]);
    $db->bind(':origin_off',  $datos["origin_off"]);
    $db->bind(':order_package',  $datos["order_package"]);
    $db->bind(':order_item_category',  $datos["order_item_category"]);
    $db->bind(':order_courier',  $datos["order_courier"]);
    $db->bind(':order_service_options',  $datos["order_service_options"]);
    $db->bind(':order_deli_time',  $datos["order_deli_time"]);
    $db->bind(':order_payment_method',  $datos["order_payment_method"]);
    $db->bind(':status_courier',  $datos["status_courier"]);
    $db->bind(':driver_id',  $datos["driver_id"]);
    $db->bind(':due_date',   $datos["due_date"]);
    $db->bind(':status_invoice',   $datos["status_invoice"]);
    $db->bind(':volumetric_percentage',   $datos["volumetric_percentage"]);

    $db->cdp_execute();
    return $db->dbh->lastInsertId();
}


function cdp_insertCourierPickupFromCustomer($datos)
{
    $db = new Conexion;

    $db->cdp_query("
    INSERT INTO cdb_add_order 
    (
        user_id,
        order_prefix,
        order_no,
        order_date,
        sender_id,
        sender_address_id,
        receiver_id,                
        receiver_address_id,
        volumetric_percentage,
        order_datetime,
        order_item_category, 
        order_package,
        status_courier,
        is_pickup,
        due_date,
        status_invoice,
        order_incomplete                 
        )
    VALUES
        (
        :user_id,
        :order_prefix,
        :order_no,
        :order_date,
        :sender_id,
        :sender_address_id,
        :receiver_id,       
        :receiver_address_id,    
        :volumetric_percentage,
        :order_datetime,
        :order_item_category, 
        :order_package,
        :status_courier,
        :is_pickup,
        :due_date,
        :status_invoice,
        :order_incomplete         

        )
");

    $db->bind(':user_id',  $datos['user_id']);
    $db->bind(':order_prefix',  $datos['order_prefix']);
    $db->bind(':order_incomplete',  $datos['order_incomplete']);
    $db->bind(':is_pickup',  $datos['is_pickup']);
    $db->bind(':order_no', $datos["order_no"]);
    $db->bind(':order_datetime',  $datos['order_datetime']);
    $db->bind(':sender_id',  $datos["sender_id"]);
    $db->bind(':receiver_id',  $datos["recipient_id"]);
    $db->bind(':sender_address_id',  $datos["sender_address_id"]);
    $db->bind(':receiver_address_id',  $datos["recipient_address_id"]);
    $db->bind(':order_date',  $datos['order_date']);
    $db->bind(':order_package',  $datos["order_package"]);
    $db->bind(':order_item_category',  $datos["order_item_category"]);
    $db->bind(':status_courier',  $datos["status_courier"]);
    $db->bind(':due_date',   $datos["due_date"]);
    $db->bind(':status_invoice',   $datos["status_invoice"]);
    $db->bind(':volumetric_percentage',   $datos["volumetric_percentage"]);

    $db->cdp_execute();
    return $db->dbh->lastInsertId();
}

function cdp_updateCourierShipmentFromCustomer($datos)
{
    $db = new Conexion;

    $db->cdp_query("
        UPDATE  cdb_add_order SET   

        driver_id =:driver_id,
        sender_id =:sender_id,
        receiver_id =:receiver_id,  
        sender_address_id=:sender_address_id,
        receiver_address_id =:receiver_address_id,
        agency =:agency,
        origin_off =:origin_off,
        order_package =:order_package,
        order_item_category =:order_item_category,
        order_courier =:order_courier,
        order_service_options =:order_service_options,
        order_deli_time =:order_deli_time,                   
        order_payment_method =:order_payment_method,
        status_courier =:status_courier,
        due_date=:due_date,
        status_invoice=:status_invoice,
        order_incomplete=:order_incomplete,
        manual_tariff=:manual_tariff
        
        WHERE
        order_id=:order_id
");

    $db->bind(':manual_tariff',  $datos['manual_tariff']);
    $db->bind(':order_incomplete',  $datos['order_incomplete']);
    $db->bind(':order_id',  $datos['order_id']);
    $db->bind(':driver_id',  $datos["driver_id"]);
    $db->bind(':sender_id',  $datos["sender_id"]);
    $db->bind(':receiver_id',  $datos["recipient_id"]);
    $db->bind(':sender_address_id',  $datos["sender_address_id"]);
    $db->bind(':receiver_address_id',  $datos["recipient_address_id"]);
    $db->bind(':agency',  $datos["agency"]);
    $db->bind(':origin_off',  $datos["origin_off"]);
    $db->bind(':order_package',  $datos["order_package"]);
    $db->bind(':order_item_category',  $datos["order_item_category"]);
    $db->bind(':order_courier',  $datos["order_courier"]);
    $db->bind(':order_service_options',  $datos["order_service_options"]);
    $db->bind(':order_deli_time',  $datos["order_deli_time"]);
    $db->bind(':order_payment_method',  $datos["order_payment_method"]);
    $db->bind(':status_courier',  $datos["status_courier"]);
    $db->bind(':due_date',   $datos["due_date"]);
    $db->bind(':status_invoice',   $datos["status_invoice"]);

    return $db->cdp_execute();
}

function cdp_updateCourierShipment($datos)
{
    $db = new Conexion;

    $db->cdp_query("
        UPDATE  cdb_add_order SET   

        driver_id =:driver_id,
        sender_id =:sender_id,
        receiver_id =:receiver_id,  
        sender_address_id=:sender_address_id,
        receiver_address_id =:receiver_address_id,
        agency =:agency,
        origin_off =:origin_off,
        order_package =:order_package,
        order_item_category =:order_item_category,
        order_courier =:order_courier,
        order_service_options =:order_service_options,
        order_deli_time =:order_deli_time,                   
        order_payment_method =:order_payment_method,
        status_courier =:status_courier,
        due_date=:due_date,
        status_invoice=:status_invoice,
        manual_tariff=:manual_tariff

        WHERE
        order_id=:order_id
");

    $db->bind(':manual_tariff',  $datos['manual_tariff']);
    $db->bind(':order_id',  $datos['order_id']);
    $db->bind(':driver_id',  $datos["driver_id"]);
    $db->bind(':sender_id',  $datos["sender_id"]);
    $db->bind(':receiver_id',  $datos["recipient_id"]);
    $db->bind(':sender_address_id',  $datos["sender_address_id"]);
    $db->bind(':receiver_address_id',  $datos["recipient_address_id"]);
    $db->bind(':agency',  $datos["agency"]);
    $db->bind(':origin_off',  $datos["origin_off"]);
    $db->bind(':order_package',  $datos["order_package"]);
    $db->bind(':order_item_category',  $datos["order_item_category"]);
    $db->bind(':order_courier',  $datos["order_courier"]);
    $db->bind(':order_service_options',  $datos["order_service_options"]);
    $db->bind(':order_deli_time',  $datos["order_deli_time"]);
    $db->bind(':order_payment_method',  $datos["order_payment_method"]);
    $db->bind(':status_courier',  $datos["status_courier"]);
    $db->bind(':due_date',   $datos["due_date"]);
    $db->bind(':status_invoice',   $datos["status_invoice"]);

    return $db->cdp_execute();
}


function cdp_updateCourierShipmentTotals($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_add_order SET
    
    tax_value=:tax_value,
    tax_discount=:tax_discount,
    tax_insurance_value=:tax_insurance_value,
    total_insured_value=:total_insured_value,
    tax_custom_tariffis_value=:tax_custom_tariffis_value,
    value_weight=:value_weight,
    declared_value=:declared_value,
    total_weight=:total_weight,
    sub_total=:sub_total,
    total_tax_insurance=:total_tax_insurance,
    total_tax_custom_tariffis=:total_tax_custom_tariffis,
    total_tax=:total_tax,
    total_declared_value=:total_declared_value,
    total_fixed_value=:total_fixed_value,
    total_tax_discount=:total_tax_discount,
    total_reexp=:total_reexp,
    total_order=:total_order        

        where order_id  = :order_id
    ');

    $db->bind(':order_id', $datos['order_id']);
    $db->bind(':tax_value', $datos['tax_value']);
    $db->bind(':tax_discount', $datos['tax_discount']);
    $db->bind(':tax_insurance_value', $datos['tax_insurance_value']);
    $db->bind(':value_weight', $datos['value_weight']);
    $db->bind(':sub_total', $datos['sub_total']);
    $db->bind(':total_insured_value', $datos['total_insured_value']);
    $db->bind(':tax_custom_tariffis_value', $datos['tax_custom_tariffis_value']);
    $db->bind(':declared_value', $datos['declared_value']);
    $db->bind(':total_reexp', $datos['total_reexp']);
    $db->bind(':total_declared_value', $datos['total_declared_value']);
    $db->bind(':total_fixed_value', $datos['total_fixed_value']);
    $db->bind(':total_tax_discount', $datos['total_tax_discount']);
    $db->bind(':total_tax_insurance', $datos['total_tax_insurance']);
    $db->bind(':total_tax_custom_tariffis', $datos['total_tax_custom_tariffis']);
    $db->bind(':total_tax', $datos['total_tax']);
    $db->bind(':total_weight', $datos['total_weight']);
    $db->bind(':total_order', $datos['total_order']);

    return $db->cdp_execute();
}

function cdp_insertCourierShipmentPackages($datos)
{

    $db = new Conexion;

    $db->cdp_query("
    INSERT INTO cdb_add_order_item 
    (
    order_id,
    order_item_description,
    order_item_quantity,
    order_item_weight,
    order_item_length,
    order_item_width,
    order_item_height,
    order_item_fixed_value,
    order_item_declared_value
                    
    )
    VALUES 
    (
    :order_id,
    :order_item_description,
    :order_item_quantity,
    :order_item_weight,
    :order_item_length,
    :order_item_width,
    :order_item_height,
    :order_item_fixed_value,
    :order_item_declared_value               
    )
  ");

    $db->bind(':order_id',   $datos["order_id"]);
    $db->bind(':order_item_description',  $datos["description"]);
    $db->bind(':order_item_quantity',  $datos["qty"]);
    $db->bind(':order_item_weight',  $datos["weight"]);
    $db->bind(':order_item_length',  $datos["length"]);
    $db->bind(':order_item_width',  $datos["width"]);
    $db->bind(':order_item_height',  $datos["height"]);
    $db->bind(':order_item_fixed_value',  $datos["fixed_value"]);
    $db->bind(':order_item_declared_value',  $datos["declared_value"]);

    return  $db->cdp_execute();
}

function cdp_getPaymentMethodCourier($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_payment_methods WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}

function cdp_getSettingsCourier()
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_settings');

    $db->cdp_execute();

    return  $db->cdp_registro();
}

/**
 * Defaults from cdb_info_ship_default (id=1) for import/courier.
 * @return object|null logistics_default1, packaging_default2, courier_default3, service_default4, time_default5, pay_default6, payment_default7, status_default8
 */
function cdp_getInfoShipDefault()
{
    $db = new Conexion;
    $db->cdp_query("SELECT * FROM cdb_info_ship_default WHERE id = '1' LIMIT 1");
    $db->cdp_execute();
    return $db->cdp_registro();
}

/**
 * First branch office id and first office id for default agency/origin_off in import.
 * @return array ['agency'=>int,'origin_off'=>int]
 */
function cdp_getDefaultAgencyOrigin()
{
    $db = new Conexion;
    $db->cdp_query('SELECT id FROM cdb_branchoffices ORDER BY id ASC LIMIT 1');
    $db->cdp_execute();
    $branch = $db->cdp_registro();
    $db->cdp_query('SELECT id FROM cdb_offices ORDER BY id ASC LIMIT 1');
    $db->cdp_execute();
    $office = $db->cdp_registro();
    return [
        'agency'     => $branch ? (int)$branch->id : 0,
        'origin_off' => $office ? (int)$office->id : 0,
    ];
}

function cdp_insertCourierShipmentTrack($datos)
{

    $db = new Conexion;
    $db->cdp_query("
        INSERT INTO cdb_courier_track 
        (
            order_id,
            order_track, 
            comments,                                  
            t_date,
            status_courier,
            office_id,
            user_id
            )
        VALUES
            (
            :order_id,    
            :order_track, 
            :comments,                                     
            :t_date,
            :status_courier,
            :office,                   
            :user_id
            )
    ");

    $db->bind(':user_id',  $datos["user_id"]);
    $db->bind(':order_id', $datos["order_id"]);
    $db->bind(':order_track',  $datos["order_track"]);
    $db->bind(':t_date',  $datos["t_date"]);
    $db->bind(':status_courier', $datos["status_courier"]);
    $db->bind(':comments', $datos["comments"]);
    $db->bind(':office', $datos["office"]);

    return  $db->cdp_execute();
}

function cdp_getSenderCourier($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_users WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return $db->cdp_registro();
}

function cdp_getacceptCourier($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_users WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return $db->cdp_registro();
}

function cdp_getRecipientCourier($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_recipients WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return $db->cdp_registro();
}

function cdp_insertCourierShipmentUserHistory($datos)
{
    $db = new Conexion;
    $db->cdp_query("
                INSERT INTO cdb_order_user_history 
                (
                    user_id,
                    order_id,
                    order_track,
                    action,
                    date_history                   
                    )
                VALUES
                    (
                    :user_id,
                    :order_id,
                    :order_track,
                    :action,
                    :date_history
                    )
            ");

    $db->bind(':order_id', $datos["order_id"]);
    $db->bind(':order_track', $datos["order_track"]);
    $db->bind(':user_id', $datos["user_id"]);
    $db->bind(':action', $datos["action"]);
    $db->bind(':date_history',  $datos["date_history"]);

    return  $db->cdp_execute();
}

function cdp_insertNotification($datos)
{
    $db = new Conexion;

    $db->cdp_query("
    INSERT INTO cdb_notifications 
    (
        user_id,
        order_id,
        notification_description,
        shipping_type,
        notification_date

    )
    VALUES
        (
        :user_id,                    
        :order_id,
        :notification_description,
        :shipping_type,
        :notification_date                    
        )
");
    $db->bind(':user_id', $datos["user_id"]);
    $db->bind(':order_id', $datos["order_id"]);
    $db->bind(':notification_description', $datos["notification_description"]);
    $db->bind(':shipping_type', $datos["shipping_type"]);
    $db->bind(':notification_date',  $datos["notification_date"]);

    $db->cdp_execute();
    return $db->dbh->lastInsertId();
}

function cdp_getSenderAddress($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_senders_addresses WHERE id_addresses=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}

function cdp_getRecipientAddress($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_recipients_addresses WHERE id_addresses=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}


function cdp_getorderitemcategory($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_category WHERE id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}

/**
 * Lista todas las categorías (modos de envío) para plantillas/import.
 * @return array [{id, name_item}, ...]
 */
function cdp_getAllCategories()
{
    $db = new Conexion;
    $db->cdp_query('SELECT id, name_item FROM cdb_category ORDER BY name_item');
    $db->cdp_execute();
    $rows = $db->cdp_registros();
    return is_array($rows) ? $rows : [];
}

function cdp_insertCourierShipmentAddresses($datos)
{
    $db = new Conexion;
    $db->cdp_query("
                    INSERT INTO cdb_address_shipments
                    (
                        order_id,
                        order_track,
                        sender_country,
                        sender_state,
                        sender_city,
                        sender_zip_code,
                        sender_address,
                        recipient_country,
                        recipient_state,
                        recipient_city,
                        recipient_zip_code,
                        recipient_address
                    )
                    VALUES
                        (
                        :order_id,    
                        :order_track,
                        :sender_country,
                        :sender_state,
                        :sender_city,
                        :sender_zip_code,
                        :sender_address,
                        :recipient_country,
                        :recipient_state,
                        :recipient_city,
                        :recipient_zip_code,                
                        :recipient_address
                        )
                ");

    $db->bind(':order_id',  $datos["order_id"]);
    $db->bind(':order_track',  $datos["order_track"]);
    $db->bind(':sender_country',   $datos["sender_country"]);
    $db->bind(':sender_state',  $datos["sender_state"]);
    $db->bind(':sender_city',  $datos["sender_city"]);
    $db->bind(':sender_zip_code',   $datos["sender_zip_code"]);
    $db->bind(':sender_address',  $datos["sender_address"]);
    $db->bind(':recipient_country',   $datos["recipient_country"]);
    $db->bind(':recipient_state',   $datos["recipient_state"]);
    $db->bind(':recipient_city',   $datos["recipient_city"]);
    $db->bind(':recipient_zip_code',   $datos["recipient_zip_code"]);
    $db->bind(':recipient_address',  $datos["recipient_address"]);

    return  $db->cdp_execute();
}


// ===========================================================
//  CUSTOMERS PACKAGES
// ===========================================================

function cdp_insertCustomerPackagesItems($datos)
{

    $db = new Conexion;

    $db->cdp_query("
        INSERT INTO cdb_customers_packages_detail 
        (
        order_id,
        order_item_description,
        order_item_quantity,
        order_item_weight,
        order_item_length,
        order_item_width,
        order_item_height,
        order_item_fixed_value,
        order_item_declared_value
                        
        )
        VALUES 
        (
        :order_id,
        :order_item_description,
        :order_item_quantity,
        :order_item_weight,
        :order_item_length,
        :order_item_width,
        :order_item_height,
        :order_item_fixed_value,
        :order_item_declared_value               
        )
    ");


    $db->bind(':order_id',   $datos["order_id"]);
    $db->bind(':order_item_description',  $datos["description"]);
    $db->bind(':order_item_quantity',  $datos["qty"]);
    $db->bind(':order_item_weight',  $datos["weight"]);
    $db->bind(':order_item_length',  $datos["length"]);
    $db->bind(':order_item_width',  $datos["width"]);
    $db->bind(':order_item_height',  $datos["height"]);
    $db->bind(':order_item_fixed_value',  $datos["fixed_value"]);
    $db->bind(':order_item_declared_value',  $datos["declared_value"]);

    return  $db->cdp_execute();
}

function cdp_updateCustomerPackagesTotals($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_customers_packages SET
    
    tax_value=:tax_value,
    tax_discount=:tax_discount,
    tax_insurance_value=:tax_insurance_value,
    total_insured_value=:total_insured_value,
    tax_custom_tariffis_value=:tax_custom_tariffis_value,
    value_weight=:value_weight,
    declared_value=:declared_value,
    total_weight=:total_weight,
    sub_total=:sub_total,
    total_tax_insurance=:total_tax_insurance,
    total_tax_custom_tariffis=:total_tax_custom_tariffis,
    total_tax=:total_tax,
    total_declared_value=:total_declared_value,
    total_fixed_value=:total_fixed_value,
    total_tax_discount=:total_tax_discount,
    total_reexp=:total_reexp,
    total_order=:total_order        

        where order_id  = :order_id
    ');

    $db->bind(':order_id', $datos['order_id']);
    $db->bind(':tax_value', $datos['tax_value']);
    $db->bind(':tax_discount', $datos['tax_discount']);
    $db->bind(':tax_insurance_value', $datos['tax_insurance_value']);
    $db->bind(':value_weight', $datos['value_weight']);
    $db->bind(':sub_total', $datos['sub_total']);
    $db->bind(':total_insured_value', $datos['total_insured_value']);
    $db->bind(':tax_custom_tariffis_value', $datos['tax_custom_tariffis_value']);
    $db->bind(':declared_value', $datos['declared_value']);
    $db->bind(':total_reexp', $datos['total_reexp']);
    $db->bind(':total_declared_value', $datos['total_declared_value']);
    $db->bind(':total_fixed_value', $datos['total_fixed_value']);
    $db->bind(':total_tax_discount', $datos['total_tax_discount']);
    $db->bind(':total_tax_insurance', $datos['total_tax_insurance']);
    $db->bind(':total_tax_custom_tariffis', $datos['total_tax_custom_tariffis']);
    $db->bind(':total_tax', $datos['total_tax']);
    $db->bind(':total_weight', $datos['total_weight']);
    $db->bind(':total_order', $datos['total_order']);

    return $db->cdp_execute();
}


function cdp_insertCustomerPackages($datos) {
    $db = new Conexion;
 
    // ===== VALIDATION: Ensure required fields exist =====
    $required_fields = [
        'user_id', 'order_prefix', 'order_no', 'agency', 'origin_off',
        'sender_id', 'sender_address_id', 'order_package', 'order_courier',
        'order_service_options', 'order_deli_time', 'status_courier',
        'order_date', 'order_datetime', 'status_invoice', 'driver_id', 'volumetric_percentage'
    ];
 
    foreach ($required_fields as $field) {
        if (!isset($datos[$field])) {
            error_log("ERROR: Missing required field in cdp_insertCustomerPackages: {$field}");
            return null;
        }
    }
 
    try {
        $db->cdp_query("
            INSERT INTO cdb_customers_packages 
            (
                user_id,
                order_prefix,
                order_no,
                order_date,
                sender_id,
                sender_address_id,
                receiver_id,
                receiver_address_id,
                volumetric_percentage,
                order_datetime,
                agency,
                origin_off,
                order_item_category, 
                order_package,
                order_courier,
                order_service_options,
                order_deli_time,
                order_pay_mode,
                order_payment_method,
                status_courier,
                driver_id,
                status_invoice,
                tracking_purchase,
                provider_purchase,
                price_purchase,
                notify_whatsapp_sender,
                recipient_type,
                is_prealert
            )
            VALUES
            (
                :user_id,
                :order_prefix,
                :order_no,
                :order_date,
                :sender_id,
                :sender_address_id,
                :receiver_id,
                :receiver_address_id,
                :volumetric_percentage,
                :order_datetime,
                :agency,
                :origin_off,
                :order_item_category, 
                :order_package,
                :order_courier,
                :order_service_options,
                :order_deli_time,
                :order_pay_mode,
                :order_payment_method,
                :status_courier,
                :driver_id,
                :status_invoice,
                :tracking_purchase,
                :provider_purchase,
                :price_purchase,
                :notify_whatsapp_sender,
                :recipient_type,
                :is_prealert
            )
        ");
 
        // ===== BIND ALL PARAMETERS =====
        $db->bind(':user_id',  $datos['user_id']);
        $db->bind(':order_prefix',  $datos['order_prefix']);
        $db->bind(':order_no', $datos["order_no"]);
        $db->bind(':agency',  $datos["agency"]);
        $db->bind(':origin_off',  $datos["origin_off"]);
        $db->bind(':sender_id',  $datos["sender_id"]);
        $db->bind(':sender_address_id',  $datos["sender_address_id"]);
        // CRITICAL FIX: Map recipient_id from POST to receiver_id in database
        $db->bind(':receiver_id',  $datos["recipient_id"] ?? 0);
        $db->bind(':receiver_address_id',  $datos["recipient_address_id"] ?? 0);
        $db->bind(':order_item_category',  $datos["order_item_category"] ?? 0);
        $db->bind(':order_courier',  $datos["order_courier"]);
        $db->bind(':order_service_options',  $datos["order_service_options"]);
        $db->bind(':order_deli_time',  $datos["order_deli_time"]);
        $db->bind(':order_pay_mode',  $datos["order_payment_method"] ?? 0);
        $db->bind(':order_payment_method',  $datos["order_payment_method"] ?? 0);
        $db->bind(':status_courier',  $datos["status_courier"]);
        $db->bind(':driver_id',  $datos["driver_id"]);
        $db->bind(':order_datetime',  $datos['order_datetime']);
        $db->bind(':order_date',  $datos['order_date']);
        $db->bind(':order_package',  $datos["order_package"]);
        $db->bind(':status_invoice',   $datos["status_invoice"]);
        $db->bind(':volumetric_percentage',   $datos["volumetric_percentage"]);
        $db->bind(':tracking_purchase',  $datos["tracking_purchase"] ?? null);
        $db->bind(':provider_purchase',  $datos["provider_purchase"] ?? null);
        $db->bind(':price_purchase',  $datos["price_purchase"] ?? null);
        $db->bind(':notify_whatsapp_sender',  $datos["notify_whatsapp_sender"] ?? 0);
        // CRITICAL FIX: Bind recipient_type (NOT NULL column)
        $db->bind(':recipient_type',  $datos["recipient_type"] ?? 'recipient');
        $db->bind(':is_prealert',  $datos['is_prealert'] ?? 0);
 
        // ===== EXECUTE INSERT =====
        $execute_result = $db->cdp_execute();
 
        if (!$execute_result) {
            error_log("ERROR: cdp_execute() failed for INSERT cdb_customers_packages");
            error_log("Data: " . json_encode($datos));
            return null;
        }
 
        // ===== GET LAST INSERT ID =====
        $lastId = $db->dbh->lastInsertId();
        
        if (!$lastId || $lastId === 0 || $lastId === '0') {
            error_log("ERROR: lastInsertId() returned invalid value: " . var_export($lastId, true));
            error_log("Data: " . json_encode($datos));
            return null;
        }
 
        // ===== VERIFY RECORD WAS ACTUALLY INSERTED =====
        // CRITICAL FIX: Primary key is 'order_id' (NOT 'id')
        $verify_db = new Conexion;
        $verify_db->cdp_query("SELECT order_id FROM cdb_customers_packages WHERE order_id = :id LIMIT 1");
        $verify_db->bind(':id', $lastId);
        $verify_db->cdp_execute();
        $verify_row = $verify_db->cdp_registro();
 
        if (!$verify_row) {
            error_log("CRITICAL ERROR: Insert returned ID {$lastId} but record not found in cdb_customers_packages!");
            error_log("Data: " . json_encode($datos));
            return null;
        }
 
        error_log("SUCCESS: Shipment created with ID: {$lastId}");
        return $lastId;
 
    } catch (Exception $e) {
        error_log("EXCEPTION in cdp_insertCustomerPackages: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("Data: " . json_encode($datos));
        return null;
    }
}

function cdp_getCustomerPackage($id)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_customers_packages WHERE order_id=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}

function cdp_deleteCustomersPackagesItems($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_customers_packages_detail WHERE order_id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function cdp_updateCustomerPackages($datos)
{
    $db = new Conexion;

    $db->cdp_query("
        UPDATE  cdb_customers_packages SET   

        agency =:agency,
        origin_off =:origin_off,
        sender_id =:sender_id,
        sender_address_id=:sender_address_id,
        tracking_purchase=:tracking_purchase,
        provider_purchase=:provider_purchase,
        price_purchase=:price_purchase,
        order_package =:order_package,
        order_item_category =:order_item_category,
        order_courier =:order_courier,
        order_service_options =:order_service_options,
        order_deli_time =:order_deli_time,                   
        status_courier =:status_courier

        WHERE
        order_id=:order_id
");

    $db->bind(':agency',  $datos["agency"]);
    $db->bind(':origin_off',  $datos["origin_off"]);
    $db->bind(':sender_id',  $datos["sender_id"]);
    $db->bind(':sender_address_id',  $datos["sender_address_id"]);
    $db->bind(':tracking_purchase',  $datos["tracking_purchase"]);
    $db->bind(':provider_purchase',  $datos["provider_purchase"]);
    $db->bind(':price_purchase',  $datos["price_purchase"]);
    $db->bind(':order_package',  $datos["order_package"]);
    $db->bind(':order_item_category',  $datos["order_item_category"]);
    $db->bind(':order_courier',  $datos["order_courier"]);
    $db->bind(':order_service_options',  $datos["order_service_options"]);
    $db->bind(':order_deli_time',  $datos["order_deli_time"]);
    $db->bind(':status_courier',  $datos["status_courier"]);
    $db->bind(':order_id',  $datos['order_id']);

    return $db->cdp_execute();
}


function cdp_updatedPreAlertPackage($pre_alert_id)
{
    $db = new Conexion;

    $db->cdp_query("
        UPDATE  cdb_pre_alert SET   
        is_package =:is_package

        WHERE pre_alert_id=:pre_alert_id          
");

    $db->bind(':pre_alert_id',  $pre_alert_id);
    $db->bind(':is_package', '1');


    return $db->cdp_execute();
}

function cdp_getPreAlertByTracking($tracking)
{
    $db = new Conexion;
    $db->cdp_query("SELECT tracking FROM cdb_pre_alert WHERE tracking = :tracking");
    $db->bind(':tracking', $tracking);
    $db->cdp_execute();

    return  $db->cdp_registro();
}


function cdp_updateItemConsolidatePackages($datos)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate_packages SET                
           
            is_consolidate ='0'          

            where  order_id=:id      
        ");



    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_updateConsolidateDeletePackages($id)
{

    $db = new Conexion;

    $db->cdp_query("UPDATE cdb_consolidate_packages SET                
           
            is_consolidate ='0'          

            where  order_id=:id      
        ");



    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function cdp_getItemdeleteConsolidatePackages($datos)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_consolidate_packages_detail WHERE consolidate_id=:id');

    $db->bind(':id', $datos['id']);

    $db->cdp_execute();

    $data = $db->cdp_registros();

    return $data;
}


function cdp_deleteItemConsolidatePackages($datos)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM cdb_consolidate_packages_detail WHERE order_id=:id');
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function cdp_getConsolidatePrintMultiplePackage($id)
{
    $db = new Conexion;


    $db->cdp_query('SELECT * FROM cdb_consolidate_packages WHERE c_no=:id');

    $db->bind(':id', $id);

    $db->cdp_execute();

    $data = $db->cdp_registro();
    $rowCount = $db->cdp_rowCount();

    $datos = [
        'data' => $data,
        'rowCount' => $rowCount
    ];

    return $datos;
}


function updateApiWhatsConfig($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_settings SET
    
            api_ws_url =:api_ws_url,                
            api_ws_token =:api_ws_token,            
            active_whatsapp =:active_whatsapp                
        ');
    $db->bind(':api_ws_url', $datos['api_ws_url']);
    $db->bind(':api_ws_token', $datos['api_ws_token']);
    $db->bind(':active_whatsapp', $datos['active_whatsapp']);
    return $db->cdp_execute();
}


function updateTemplatesWhatsaApp($datos)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE whatsapp_templates SET
          title=:title,
          description=:description,
          body=:body  
         where id=:id');


    $db->bind(':title', $datos['title']);
    $db->bind(':description', $datos['description']);
    $db->bind(':body', $datos['body']);
    $db->bind(':id', $datos['id']);

    return $db->cdp_execute();
}


function addTemplatesWhatsaApp($datos)
{
    $db = new Conexion;

    $db->cdp_query(
        'INSERT INTO whatsapp_templates
     (
          title,
          description,
          body       
    )
    VALUES (
      :title,
      :description,
      :body
      )'
    );


    $db->bind(':title', $datos['title']);
    $db->bind(':description', $datos['description']);
    $db->bind(':body', $datos['body']);

    return $db->cdp_execute();
}


function getWhatAppTemplates($id)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM whatsapp_templates WHERE  id=:id");
    $db->bind(':id', $id);
    $db->cdp_execute();
    return $result = $db->cdp_registro();
}


function deleteTemplateWhatsApp($id)
{
    $db = new Conexion;

    $db->cdp_query('DELETE  FROM whatsapp_templates WHERE id=:id');
    $db->bind(':id', $id);

    return $db->cdp_execute();
}


function getDefaultTemplateActiveWhatsApp($id)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM default_notification_templates WHERE id =:id");

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}


function getTemplateWhatsApp($id)
{
    $db = new Conexion;

    $db->cdp_query("SELECT * FROM whatsapp_templates WHERE id =:id");

    $db->bind(':id', $id);

    $db->cdp_execute();

    return  $db->cdp_registro();
}


function updateDefaultTemplateWhatsApp($datos)
{

    $db = new Conexion;

    $db->cdp_query('UPDATE default_notification_templates SET
    
            id_template =:id_template,
            active =:active
            WHERE id=:id            
            
        ');


    $db->bind(':id_template', $datos['id_template']);
    $db->bind(':active', $datos['active']);
    $db->bind(':id', $datos['id']);




    return $db->cdp_execute();
}

function updateCourierStatusFromTracking($status, $order_id)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_add_order SET    
            status_courier =:status_courier                    
            where  order_id=:order_id     
        ');

    $db->bind(':status_courier', $status);
    $db->bind(':order_id', $order_id);

    return $db->cdp_execute();
}


function insertCourierShipmentTrack($datos)
{

    $db = new Conexion;
    $db->cdp_query("
        INSERT INTO cdb_courier_track 
        (
            order_track, 
            comments,                                  
            t_date,
            status_courier,
            office_id,
            user_id
            )
        VALUES
            (
            :order_track, 
            :comments,                                     
            :t_date,
            :status_courier,
            :office,                   
            :user_id
            )
    ");

    $db->bind(':user_id',  $datos["user_id"]);
    $db->bind(':order_track',  $datos["order_track"]);
    $db->bind(':t_date',  $datos["t_date"]);
    $db->bind(':status_courier', $datos["status_courier"]);
    $db->bind(':comments', $datos["comments"]);
    $db->bind(':office', $datos["office"]);

    return  $db->cdp_execute();
}


function updateCourierStatusDelivered($data)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_add_order SET    
                         
    status_courier =:status_courier,
    person_receives=:person_receives,
    photo_delivered=:photo_delivered
    where  order_id=:order_id      
');

    $db->bind(':status_courier', $data['status_courier']);
    $db->bind(':person_receives', $data['person_receives']);
    $db->bind(':order_id', $data['shipment_id']);
    $db->bind(':photo_delivered', $data['photo_delivered']);


    return $db->cdp_execute();
}


function updateCustomerPackagesStatusFromTracking($status, $order_id)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_customers_packages SET    
            status_courier =:status_courier                    
            where  order_id=:order_id     
        ');

    $db->bind(':status_courier', $status);
    $db->bind(':order_id', $order_id);

    return $db->cdp_execute();
}


function updateCustomerPackagesStatusDelivered($data)
{
    $db = new Conexion;

    $db->cdp_query('UPDATE cdb_customers_packages SET    
                         
    status_courier =:status_courier,
    person_receives=:person_receives,
    photo_delivered=:photo_delivered
    where  order_id=:order_id      
');

    $db->bind(':status_courier', $data['status_courier']);
    $db->bind(':person_receives', $data['person_receives']);
    $db->bind(':order_id', $data['shipment_id']);
    $db->bind(':photo_delivered', $data['photo_delivered']);


    return $db->cdp_execute();
}

function cdp_insertPackageTracking($order_id, $user_id, $tracking_number, $estimated_eta) {
    $db = new Conexion;

    $db->cdp_query('INSERT INTO cdb_package_tracking_number
        (
            order_id,
            user_id,
            tracking_number,
            estimated_eta
        )
        VALUES (
            :order_id,
            :user_id,
            :tracking_number,
            :estimated_eta
        )');

    $db->bind(':order_id', $order_id);
    $db->bind(':user_id', $user_id);
    $db->bind(':tracking_number', $tracking_number);
    $db->bind(':estimated_eta', $estimated_eta);

    return $db->cdp_execute();
}

function cdp_getPackageTracking($order_id) {
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_package_tracking_number WHERE order_id = :order_id');
    $db->bind(':order_id', $order_id);

    return $db->cdp_registro();
}

function cdp_updatePackageTracking($order_id, $tracking_number = null, $estimated_eta = null) {
    $db = new Conexion;

    $tracking = cdp_getPackageTracking($order_id);

    if (!$tracking) {
        return false;
    }

    $fields = [];
    $params = [':order_id' => $order_id];

    $trackingNumberProvided = func_num_args() >= 2 && $tracking_number !== null;
    $etaProvided = func_num_args() >= 3 && $estimated_eta !== null;

    if ($trackingNumberProvided && $tracking->tracking_number != $tracking_number) {
        $fields[] = 'tracking_number = :tracking_number';
        $params[':tracking_number'] = $tracking_number;
    }

    if ($etaProvided && $tracking->estimated_eta != $estimated_eta) {
        $fields[] = 'estimated_eta = :estimated_eta';
        $params[':estimated_eta'] = $estimated_eta;
    }

    if (empty($fields)) {
        return true;
    }

    $sql = 'UPDATE cdb_package_tracking_number SET ' . implode(', ', $fields) . ' WHERE order_id = :order_id';
    $db->cdp_query($sql);

    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }

    return $db->cdp_execute();
}

function cdp_updateUserStatus4234sf($userId, $status)
{
    $db = new Conexion;

    // Prepare the update query
    $db->cdp_query('UPDATE cdb_users SET active = :active WHERE id = :id');
    
    // Bind parameters
    $db->bind(':active', $status);
    $db->bind(':id', $userId);

    // Execute and return the result
    return $db->cdp_execute();
}

function approveUser($id) {
    $db = new Conexion;

    // Update both 'approve' and 'active' columns
    $db->cdp_query("UPDATE cdb_users SET
            approve = :approve,
            active = :active
            WHERE id = :id
        ");
    
    $db->bind(':id', $id);
    $db->bind(':approve', 1);
    $db->bind(':active', 1);

    if ($db->cdp_execute()) {
        return true;
    } else {
        return false;
    }
}