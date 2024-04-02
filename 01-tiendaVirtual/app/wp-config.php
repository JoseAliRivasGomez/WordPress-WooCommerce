<?php
define( 'WP_CACHE', true );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'tiendavirtualdb' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'myrootpassword' );

/** Database hostname */
define( 'DB_HOST', 'mysql' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

define( 'FS_METHOD', 'direct' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'c<C?z;&*#kQ5,h0z1gM^2+LjpAYdGgR3dC>~J&~Plg<6)B7kbWOksQfnOSCOwXwe' );
define( 'SECURE_AUTH_KEY',  '0QtX0>S!W56>!${1w&}<JjQv35tTTm6UK[wuGEPNV}udk2]i=EIru0|mbbba!8XY' );
define( 'LOGGED_IN_KEY',    'cd,io~zfr}.:S^_8-&8(G-wd%|Pf?`BXxM6{ep7Zk?!-D=>_t>)zM%>pWWADGiWt' );
define( 'NONCE_KEY',        'NXopt1q]z!+xpVN*_iU/!$9G-`PdSn]ZOlMjRax`5Qr&)sP&?c[*nDo[9AO$KN?,' );
define( 'AUTH_SALT',        '^7JLbO.LuYSQJ[v.TN$yNr^F_gLE17v`hlVp#1_KdEX~0D<w(Ot;Bs1bfJr6OzPt' );
define( 'SECURE_AUTH_SALT', '(nv0]#IYsLHIBv`oxWI(9Oq[nj%KUI4MhDNH6c$g|Y[wzGWy<z{R&Y7GsQR!y3H(' );
define( 'LOGGED_IN_SALT',   '|%1@NMaF>OLyZ9oi+^yUXRzU#KEXpOg~UsEn4jv+UE(b/7tl$EWz/grU|eHv$Yb8' );
define( 'NONCE_SALT',       '4b;J>fiMR~<U^{MY[sZgdwXPQJ^Y]T!PkzrE&q<>$m6aW[oL4d111al,v0H!,?N&' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
