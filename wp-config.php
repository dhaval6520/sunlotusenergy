<?php
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
define( 'DB_NAME', 'sunlotusenergy' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

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
define( 'AUTH_KEY',         'H*}hNt2: )0(gdVw+.)txru{ES@T1*I{%TEOOr5D5n9*;=@oWRi.bI&UFw!gU+C]' );
define( 'SECURE_AUTH_KEY',  'F=j0j;@cN]dvw^Oj$KifFT*GZ> &7l@gCY%gnC5TlL}0[1zK)![VLF$RgH7.!f!y' );
define( 'LOGGED_IN_KEY',    '*:RlFaO+y3Jm)V=sm@ZkVt>7oE4=V#Gt^R`q]#`dUrc`noF!Kspz7 DMNlq^)tWE' );
define( 'NONCE_KEY',        '1h+*)p+{Q*2Z2liPc[tU^79mJmE{F;Wp3&1K*]3ieC=25_ cS:1aQi#I|E$(>Zt?' );
define( 'AUTH_SALT',        '`Pz=Q-lesT^}nI.O``2#2TR!)SZX%mab;oJtFoMEeKz<41VzfIO#05OW|%;?i;4&' );
define( 'SECURE_AUTH_SALT', 'g</YMpd?N&blb6?pr;VKUB=DX=#1o$[#I<|g_<f-}`X>49M~l1%@yb<Si|rH+s9=' );
define( 'LOGGED_IN_SALT',   'mY<L5c]a25mRl*QR>_E~ YB/HGx^X8B6onkyjUKB$r(#pDf_B{AR1ueElqB*N fF' );
define( 'NONCE_SALT',       '=8jN?+F6t&_032s`W^$WK0bT2GoC~`?l_ h-h f17STCD~$m..),=%*OI>V2B>rQ' );

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
