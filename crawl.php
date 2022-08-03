<?php
include("config.php");
include("classes/DomDocumentParser.php");

$alreadyCrawled = array();
$crawling = array();
$alreadyFoundImages = array();

function LinkExists($url)
{
    global $con;

    $query = $con->prepare("SELECT * FROM sites WHERE `name` LIKE '%SEARCH%'");
    $query = $con->prepare("SELECT * FROM images WHERE `name` LIKE '%SEARCH%'");

    $query->bindParam(":url", $url);
    $query->execute();

    return $query->rowCount() != 0;
}
function insertLink($url, $title, $description, $keywords)
{
    global $con;

    $query = $con->prepare("INSERT INTO sites(url, title, description, keywords)
                            VALUES(:url, :title, :description, :keywords)");

    $query->bindParam(":url", $url);
    $query->bindParam(":title", $title);
    $query->bindParam(":description", $description);
    $query->bindParam(":keywords", $keywords);

    return $query->execute();
}
function insertImage($url, $src, $alt, $title) {
    global $con;

    $query = $con->prepare("INSERT INTO images(siteUrl, imageUrl, alt, title)
                            VALUES(:siteUrl, :imageUrl, :alt, :title)");

    $query->bindParam(":siteUrl", $url);
    $query->bindParam(":imageUrl", $src);
    $query->bindParam(":alt", $alt);
    $query->bindParam(":title", $title);

    return $query->execute();
}

function createlink($src, $url)

{

    $scheme =  parse_url($url)["scheme"];
    $host = parse_url($url)["host"];

    if (substr($src, 0, 2) == "//") {
        $src = $scheme . ":" . $src;
    } else if (substr($src, 0, 1) == "/") {
        $src = $scheme . "://" . $host . $src;
    } else if (substr($src, 0, 2) == "./") {
        $src = $scheme . "://" . $host . dirname(parse_url($url)["path"]) . substr($src, 1);
    } else if (substr($src, 0, 3) == "../") {
        $src = $scheme . "://" . $host . "/" . $src;
    } else if (substr($src, 0, 5) != "https" && substr($src, 0, 4) != "http") {
        $src = $scheme . "://" . $host . "/" . $src;
    }

    return $src;
}

function getDetails($url){

 global $alreadyFoundImages;

    $parser = new DomDocumentParser($url);

    $titleArray = $parser->getTitleTags();

    if (sizeof($titleArray) == 0 || $titleArray->item(0) == NULL) {
        return;
    }

    $title = $titleArray->item(0)->nodeValue;
    $title = str_replace("\n", "", $title);

    if ($title == "") {
        return;
    }

    $description = "";
    $keywords = "";

    $metasArray = $parser->getMetatags();

    foreach ($metasArray as $meta) {


        if ($meta->getAttribute("name") == "description") {
            $description = $meta->getAttribute("content");
        }

        if ($meta->getAttribute("name") == "keywords") {
            $keywords = $meta->getAttribute("content");
        }
    }

    $description = str_replace("\n", "", $description);
    $keywords = str_replace("\n", "", $keywords);

    if (LinkExists($url)) {
        echo "$url already  exists<br>";
    } else if (insertLink($url, $title, $description, $keywords)) {
        echo "SUCCESS: $url";
    } else {
        echo  "ERROR: Failed to insert <br> $url <br> $title <br> $description <br> $keywords"; 
    }

    $imageArray = $parser->getImages();
    foreach($imageArray as $image) {
        $src = $image->getAttribute("src");
        $alt = $image->getAttribute("alt");
        $title = $image->getAttribute("title");

        if(!$title && !$alt){
         echo "<br><br>continued<br> src:$src <br> alt:$alt <br> title:$title";
            continue;
        }

        $src = createLink($src, $url);
    
        if(!in_array($src, $alreadyFoundImages)) {
            $alreadyCrawled[] = $src;

         insertImage($url, $src, $alt, $title);

        }
    }

}

function followLinks($url)
{

    global $alreadyCrawled;
    global $crawling;

    $parser = new DomDocumentParser($url);

    $linklist = $parser->getLinks();

    foreach ($linklist as $link) {
        $href = $link->getAttribute("href");

        if (strpos($href, "#") !== false) {
            continue;
        } else if (substr($href, 0, 11) == "javascript:") {
            continue;
        }


        $href = createlink($href, $url);

 
        if (!in_array($href, $alreadyCrawled)) {
            $alreadyCrawled[] = $href;
            $crawling[] = $href;

            getDetails($href);
        } else return;
    }
    array_shift($crawling);

    foreach ($crawling as $site) {
        followLinks($site);
    }
}

$startUrl = "http://www.BBC.com";
followLinks($startUrl);
