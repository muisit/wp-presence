=== Role Based Access Manager: Media Protector ===
Contributors: muisit
Tags: attachments, media, roles, security
Requires at least: 5.4
Tested up to: 5.4
Stable tag: trunk
Requires PHP: 7.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Role Based Access Management for Media files (attachments).

== Description ==

# Role Base Access Manager: Media Protector
WordPress plugin to assign access roles to individual files.

This simple plugin allows administrators (anyone with access to the edit-post form for attachments/media) to set access based on roles.
The plugin provides a 'Security' meta-box on the right hand side where you can type in role names and select them (much like you add tags
to regular posts). Whenever a visitor wants to download or view a file or image from the uploads directory, his/her current roles are checked
against the configured roles.

This plugin tries to look for originals of resized and rescaled images by making a rough search in the meta data table. This allows you to
mark the original image of a blog entry for specific access and have all thumbnails and other derived images be protected as well. Please note
that this plugin does not clean up after you. If for some reason left-over thumbnails remain in the upload directory, the plugin cannot find
them in the database and will allow access.

## Roles
This plugin works based on role access management. That means it will try to match the specified roles on the media with the available roles of a user. However, the capabilities system of `Wordpress` is cumulative: an `Administrator` has more privileges as an `Editor`, but at least the
same. Usually, people only have one Role in this system. As this plugin does not check on capabilities, but on roles, you will need to specify
*all* the roles that should have access to this file.

Alternatively, you can add secondary roles to a User, allowing `Administrator` to also be a `Subscriber`. In this way, you only need to add the
`Subscriber` role to media files to allow it to be downloaded by all registered members. However, adding secondary roles is a manual task. If you have many users and few files, it can be easier to specifiy all roles with the media. If you have many files and few users, you had better use secondary role assignments. If you have many files and many users, you should look into a way to automatically assign roles to people using some sort of on-boarding method. If you need a plugin for that, send me a message.

## Redirections
The plugin works by inserting a redirection script in your `.htaccess` file on activation. This does not work properly for `NGinX`, in which
case you have to insert a redirection manually. Freely copied from the [https://wordpress.org/plugins/aam-protected-media-files/](AAM Protected Media Files) description:

```
location ~* ^/wp-content/uploads/ {
   rewrite (?i)^(/wp-content/uploads/.*)$ /index.php?rbam-media=1 last;
   return 307;
}
```

The plugin will try to read the accessed file from the original request and apply role based access management on it.

== Frequently Asked Questions ==

= Is there a limit on the number of roles I can assign =

There is no practical limit imposed by this plugin.

== Screenshots ==

1. The plugin adds an additional meta-box to the edit form of Media files

== Changelog ==

= 1.1.0 =
* Renamed 'Security' to 'Authorization', which covers the meta-box task better

= 1.0 =
* Initial version

== Upgrade Notice ==

= 1.0 =
Initial version

