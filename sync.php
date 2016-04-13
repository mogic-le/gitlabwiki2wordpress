#!/usr/bin/env php
<?php
/**
 * Sync gitlab wiki pages as pages into wordpress
 *
 * @author Christian Weiske <weiske@mogic.com>
 */
require_once __DIR__ . '/config.php';

foreach ($GLOBALS['wikis'] as $gitUrl => $mainPageId) {
    sync_wiki($gitUrl, $mainPageId);
}

function sync_wiki($gitUrl, $mainPageId)
{
    logMsg('Processing ' . $gitUrl);
    $pathName = str_replace('/', '-', $gitUrl);
    $path = __DIR__ . '/tmp/' . $pathName . '/';
    if (!is_dir(__DIR__ . '/tmp/')) {
        mkdir(__DIR__ . '/tmp');
    }
    if (!is_dir($path)) {
        exec('git clone ' . escapeshellarg($gitUrl) . ' ' . escapeshellarg($path) . ' 2>&1');
    } else {
        chdir($path);
        exec('git pull' . ' 2>&1');
    }

    $wikiBaseUrl = getWikiBaseFromGitUrl($gitUrl);
    $imgBaseUrl = substr($wikiBaseUrl, 0, -6);//strip wikis/

    $pages = listPages($mainPageId);
    chdir($path);
    $files = glob('*.md');
    foreach ($files as $file) {
        if ($file == 'home.md') {
            $title = $mainPageId;
        } else {
            $title = substr($file, 0, -3);
        }

        $wikiUrl     = $wikiBaseUrl . substr($file, 0, -3);
        $wikiContent = file_get_contents($file);
        $wikiContent = str_replace(
            '](/uploads/',
            '](' . $imgBaseUrl . 'uploads/',
            $wikiContent
        );

        $content = '[markdown]' . $wikiContent . '[/markdown]'
            . '<hr/>'
            . '<p class="gitlabedit">'
            . '<a href="' . htmlspecialchars($wikiUrl) . '">view</a>'
            . ' or <a href="' . htmlspecialchars($wikiUrl . '/edit') . '">edit</a>'
            . ' in GitLab'
            . '</p>';

        if (isset($pages[$title])) {
            //page exists already in wordpress
            $needsupdate = false;
            if ($pages[$title]->content->rendered == '') {
                //page is empty
                $needsupdate = true;
            } else if (strtotime($pages[$title]->modified) < filemtime($file)) {
                //content changed
                $needsupdate = true;
            }

            if ($needsupdate) {
                logMsg("Updating $file");
                $json = array(
                    'content' => $content,
                    'date'    => date('c', filectime($file)),
                    'status'  => 'publish',
                );
                postWordpressData(
                    '/?rest_route=/wp/v2/pages/' . intval($pages[$title]->id),
                    $json
                );
            }
            $pages[$title]->inwp = true;
        } else {
            //page does not exist in wordpress
            logMsg("Creating $file");
            $json = array(
                'parent'  => $mainPageId,
                'content' => $content,
                'title'   => $title,
                'date'    => date('c', filectime($file)),
                'status'  => 'publish',
            );
            postWordpressData('/?rest_route=/wp/v2/pages', $json);

            $pages[$title] = (object) array(
                'inwp' => true
            );
        }
    }

    foreach ($pages as $page) {
        if (!isset($page->inwp)) {
            //page exists in gitlab, but not in wordpress
            //FIXME: delete
        }
    }
}

function logMsg($msg)
{
    if ($GLOBALS['log']) {
        echo $msg . "\n";
    }
}

function listPages($parentId)
{
    $data = getWordpressData(
        '/?rest_route=/wp/v2/pages'
        . '&per_page=100'
        . '&parent=' . intval($parentId)
        //. '&context=edit'
    );
    $pages = array();
    foreach ($data as $page) {
        $pages[$page->title->rendered] = $page;
    }

    $data = getWordpressData('/?rest_route=/wp/v2/pages/' . intval($parentId));
    $pages[$parentId] = $data;

    return $pages;
}

function getWordpressData($relUrl)
{
    $url  = getWordpressUrl($relUrl);
    $json = file_get_contents($url);
    if ($json === false) {
        throw new Exception('Error fetching URL: ' . $url);
    }
    $data = json_decode($json);
    if ($data === null) {
        throw new Exception('Error decoding JSON at ' . $url);
    }
    return $data;
}

function postWordpressData($relUrl, $payload)
{
    $url     = getWordpressUrl($relUrl);
    $context = stream_context_create(
        array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode($payload),
                //'ignore_errors' => true,
            )
        )
    );

    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        throw new Exception('Failed to POST data to wordpress; ' . $url);
    }
}

function getWordpressUrl($relUrl)
{
    return str_replace(
        '://',
        '://' . $GLOBALS['wordpress']['username']
        . ':' . $GLOBALS['wordpress']['password'] . '@',
        rtrim($GLOBALS['wordpress']['url'], '/')
    ) . $relUrl;
}

function getWikiBaseFromGitUrl($gitUrl)
{
    if (!preg_match('#^(.+)@(.+):(.+).wiki.git$#', $gitUrl, $matches)) {
        return false;
    }
    list($all, $user, $host, $path) = $matches;
    return 'https://' . $host . '/' . $path . '/wikis/';
}
?>
