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
    $pathName = str_replace('/', '-', $gitUrl);
    $path = __DIR__ . '/tmp/' . $pathName . '/';
    if (!is_dir(__DIR__ . '/tmp/')) {
        mkdir(__DIR__ . '/tmp');
    }
    if (!is_dir($path)) {
        exec('git clone ' . escapeshellarg($gitUrl) . ' ' . escapeshellarg($path));
    } else {
        chdir($path);
        exec('git pull');
    }

    $pages = listPages($mainPageId);
    chdir($path);
    $files = glob('*.md');
    foreach ($files as $file) {
        if ($file == 'home.md') {
            $title = $mainPageId;
        } else {
            $title = substr($file, 0, -3);
        }

        $content = '[markdown]' . file_get_contents($file) . '[/markdown]';
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
                echo "Updating $file\n";
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
            echo "Creating $file\n";
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
?>
