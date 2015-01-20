=== BIM Collaboration Forum ===
Contributors: Bastiaan Grutters, Léon van Berlo
Donate link: http://opensourcebim.org/
Tags: BIM, openBIM, BCF, BIM collaboration Format, BIM collaboration Forum
Requires at least: 4.0
Tested up to: 4.1
Stable tag: 0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This turns your Wordpress site into a full blown BIM Collaboration discussion forum. 

== Description ==

This turns your Wordpress site into a full blown BIM Collaboration discussion forum. 
Features include uploading BCF data, viewing, commenting, etc.


You need a BIMSie compliant server for IFC data (for example open source BIMserver.org). 


== Installation ==


1. Upload the zip file throuhh the 'Plugins' menu in WordPress and activate it.
2. Get an API key at http://shop.opensourcebim.org 
3. Activate your API key  under 'settings' -> 'BCF license activation' or via /wp-admin/options-general.php?page=bim_bcf_management_dashboard
4. Create pages and use these shortcuts: [showAddIssueForm], [showAddZipForm], [showBCFViewer], [showIssue], [showIssues], [showMyIssues]
5. Configure options under 'settings' -> 'BCF Management options' or use /wp-admin/options-general.php?page=bim_bcf_management_options In this section you can add BCF Topic statuses, types, labels, etc. See the BCF documentation for more info about this.
6. Go to the [showIssues] page to configure access credentials to an IFC datasource (for example your bimserver.org installation)


== Frequently Asked Questions ==

= Can I view the mmodel? =

Yes, this forum uses BIM Surfer for that.

= Is this BIMSie compliant? =

Yes.

= Is this compliant with the BCF REST API? =

No. We find it difficult to implement that API because it is build for tools that both have BCF and IFC data in the same system. This BCF Forum connects to IFC on another server and the BCF REST API is not build for that use-case.

== Screenshots ==

1. A screenshot of the BCF forum.

== Changelog ==

= 0.2 = 
* small update

= 0.1 =
* Initial release

