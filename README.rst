*************************************
Sync GitLab wiki pages into Wordpress
*************************************

Copies all pages of a GitLab wiki as pages into wordpress.

The script uses the Wordpress REST API to add new pages,
directly storing the gitlab wiki markdown content into it.

Markdown rendering is done with the *Markdown shortcode* wordpress plugin.



============
Installation
============

#. Copy ``config.php.dist`` to ``config.php`` and adjust it
#. Run ``./sync.php`` via cron every hour or so


============
Dependencies
============


Sync server
===========
* git commandline
* PHP 5.x


Wordpress plugins
=================
* `Markdown shortcode <https://wordpress.org/plugins/markdown-shortcode/>`_
* `REST API <https://wordpress.org/plugins/rest-api/>`_
* `REST API Basic Auth Handler <https://github.com/WP-API/Basic-Auth>`_


=======
License
=======
``gitlabwiki2wordpress`` is licensed under the `AGPL v3 or later`__.

__ http://www.gnu.org/licenses/agpl
