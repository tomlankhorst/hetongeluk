<?php

header('Content-Type: application/json; charset=utf-8');

require_once 'initialize.php';

global $user;

$function = $_REQUEST['function'];

/**
 * @param TDatabase $database
 * @return array
 */
function getStatistics($database){
  $stats = [];

  $sql = "SELECT COUNT(*) AS count, IFNULL(SUM(personsinjured), 0) AS personsinjured, IFNULL(SUM(personsdead), 0) AS personsdead  FROM accidents WHERE DATE (`date`) = CURDATE();";
  $stats['today'] = $database->fetch($sql);

  $sql = "SELECT COUNT(*) AS count, IFNULL(SUM(personsinjured), 0) AS personsinjured, IFNULL(SUM(personsdead), 0) AS personsdead  FROM accidents WHERE DATE (`date`) = subdate(CURDATE(), 1);";
  $stats['yesterday'] = $database->fetch($sql);

  $sql = "SELECT COUNT(*) AS count, IFNULL(SUM(personsinjured), 0) AS personsinjured, IFNULL(SUM(personsdead), 0) AS personsdead  FROM accidents WHERE DATE (`date`) > subdate(CURDATE(), 7)";
  $stats['last7days']  = $database->fetch($sql);

  $sql = "SELECT COUNT(*) AS count, IFNULL(SUM(personsinjured), 0) AS personsinjured, IFNULL(SUM(personsdead), 0) AS personsdead  FROM accidents WHERE YEARWEEK(`date`, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)";
  $stats['lastweek']  = $database->fetch($sql);

  $sql = "SELECT COUNT(*) AS count, IFNULL(SUM(personsinjured), 0) AS personsinjured, IFNULL(SUM(personsdead), 0) AS personsdead  FROM accidents WHERE YEAR(`date`) = YEAR(CURDATE())";
  $stats['thisyear']  = $database->fetch($sql);

  return $stats;
}

/**
 * @param TDatabase $database
 * @param string $url
 * @throws Exception
 * @return array | false
 */
function urlExists($database, $url){
  $sql = "SELECT id, accidentid FROM articles WHERE url=:url LIMIT 1;";
  $params = [':url' => $url];
  $DBResults = $database->fetchAll($sql, $params);
  foreach ($DBResults as $found) {
    return [
      'articleid'  => (int)$found['id'],
      'accidentid' => (int)$found['accidentid'],
      ];
  }
  return false;
}

if ($function == 'login') {
  if (is_null($_REQUEST['email']) || is_null($_REQUEST['password'])) dieWithJSONErrorMessage('Invalid AJAX login call.');

  $email        = $_REQUEST['email'];
  $password     = $_REQUEST['password'];
  $stayLoggedIn = (int)getRequest('stayLoggedIn', 0) === 1;

  $user->login($email, $password, $stayLoggedIn);
  echo json_encode($user->info());
} // ====================
else if ($function == 'register') {
  try {
    $data = json_decode(file_get_contents('php://input'), true);

    $user->register($data['firstname'], $data['lastname'], $data['email'], $data['password']);
    $result = array('ok' => true);
  } catch (Exception $e) {
    $result = array('error' => $e->getMessage());
  }

  echo json_encode($result);
} // ====================
else if ($function == 'logout') {
  $user->logout();
  echo json_encode($user->info());
} // ====================
else if ($function == 'sendPasswordResetInstructions') {
  try {
    $result = [];
    if (! isset($_REQUEST['email'])) throw new Exception('No email adres');
    $email  = trim($_REQUEST['email']);

    $recoveryID = $user->resetPasswordRequest($email);
    if (! $recoveryID) throw new Exception('Interne fout: Kan geen recoveryID aanmaken');

    $domain            = DOMAIN_NAME;
    $subject           = $domain . ' wachtwoord resetten';
    $server            = SERVER_DOMAIN;
    $emailEncoded      = urlencode($email);
    $recoveryIDEncoded = urlencode($recoveryID);
    $body    = <<<HTML
<p>Hallo,</p>

<p>We hebben een verzoek ontvangen om het wachtwoord verbonden aan je emailadres ($email) te resetten. Om je wachtwoord te resetten, klik op de onderstaande link:</p>

<p><a href="$server/account/resetpassword.php?email=$emailEncoded&recoveryid=$recoveryIDEncoded">Wachtwoord resetten</a></p>

<p>Vriendelijke groeten,<br>
$domain</p>
HTML;

    if (sendEmail($email, $subject, $body, [])) $result['ok'] = true;
    else throw new Exception('Interne server fout: Kan email niet verzenden.');
  } catch (Exception $e){
    $result = array('ok' => false, 'error' => $e->getMessage());
  }
  echo json_encode($result);
} // ====================
else if ($function == 'saveNewPassword') {
  try {
    if (! isset($_REQUEST['password']))   throw new Exception('Geen password opgegeven');
    if (! isset($_REQUEST['recoveryid'])) throw new Exception('Geen recoveryid opgegeven');
    if (! isset($_REQUEST['email']))      throw new Exception('Geen email opgegeven');
    $password   = $_REQUEST['password'];
    $recoveryid = $_REQUEST['recoveryid'];
    $email      = $_REQUEST['email'];

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $sql = <<<SQL
UPDATE users SET 
  passwordhash=:passwordhash,
  passwordrecoveryid = null
WHERE email=:email 
  AND passwordrecoveryid=:passwordrecoveryid;
SQL;

    $params = array(':passwordhash' => $passwordHash, ':email' => $email, ':passwordrecoveryid' => $recoveryid);
    if (($database->execute($sql, $params, true)) && ($database->rowCount ===1)) {
      $result = ['ok' => true];
    } else $result = ['ok' => false, 'error' => 'Wachtwoord link is verlopen of email is onbekend'];
  } catch (Exception $e) {
    $result = array('ok' => false, 'error' => $e->getMessage());
  }

  echo json_encode($result);
} // ====================
else if ($function === 'loadaccidents') {
  try {
    $offset          = (int)getRequest('offset',0);
    $count           = (int)getRequest('count', 100);
    $id              = isset($_REQUEST['id'])? (int)$_REQUEST['id'] : null;
    $search          = isset($_REQUEST['search'])? $_REQUEST['search'] : '';
    $moderationsList = (int)getRequest('moderations', 0);
    $export          = (int)getRequest('export', 0);

    if ($count > 1000) throw new Exception('Internal error: Count to high.');
    if ($moderationsList && (! $user->isModerator())) throw new Exception('Moderaties zijn alleen zichtbaar voor moderators.');

    $accidents    = [];
    $articles     = [];
    $params       = [];
    $sqlModerated = '';
    if ($moderationsList) {
      $sqlModerated = ' (ac.awaitingmoderation=1) OR (ac.id IN (SELECT accidentid FROM articles WHERE awaitingmoderation=1)) ';
    } else if ($id === null) {
      // Individual pages are always shown and *not* moderated.
      $sqlModerated = $user->isModerator()? '':  ' ((ac.awaitingmoderation=0) || (ac.userid=:useridModeration)) ';
      if ($sqlModerated) $params[':useridModeration'] = $user->id;
    }

    $sql = <<<SQL
SELECT DISTINCT 
  ac.id,
  ac.userid,
  ac.createtime,
  ac.streamdatetime,
  ac.streamtopuserid,     
  ac.streamtoptype,
  ac.awaitingmoderation,
  ac.title,
  ac.text,
  ac.date,
  ac.personsdead,
  ac.personsinjured,
  ac.pedestrian, 
  ac.bicycle, 
  ac.scooter, 
  ac.motorcycle, 
  ac.car, 
  ac.taxi, 
  ac.emergencyvehicle, 
  ac.deliveryvan, 
  ac.tractor, 
  ac.bus, 
  ac.tram, 
  ac.truck, 
  ac.train,  
  ac.wheelchair, 
  ac.mopedcar, 
  ac.transportationunknown, 
  ac.child, 
  ac.pet, 
  ac.alcohol, 
  ac.hitrun, 
  ac.trafficjam, 
  ac.tree, 
  CONCAT(u.firstname, ' ', u.lastname) AS user, 
  CONCAT(tu.firstname, ' ', tu.lastname) AS streamtopuser 
FROM accidents ac
LEFT JOIN users u  on u.id  = ac.userid 
LEFT JOIN users tu on tu.id = ac.streamtopuserid
SQL;

    if ($id !== null) {
      // Single accident
      $params = ['id' => $id];
      $SQLWhere = " WHERE ac.id=:id ";

    } else if ($search !== '') {
      // Text search
      $params['search']  = $search;
      $params['search2'] = $search;

      if ($sqlModerated) $sqlModerated = ' AND ' . $sqlModerated;

      $SQLWhere = <<<SQL
 LEFT JOIN articles ar on ac.id = ar.accidentid      
 WHERE MATCH(ac.title, ac.text) AGAINST (:search  IN BOOLEAN MODE)
    OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE)
    $sqlModerated
ORDER BY streamdatetime DESC
LIMIT $offset, $count
SQL;
    } else {
      // accidents stream
      if ($sqlModerated) $sqlModerated = ' WHERE ' . $sqlModerated;
      $SQLWhere = " $sqlModerated ORDER BY streamdatetime DESC LIMIT $offset, $count ";
    }

    $sql .= $SQLWhere;
    $ids = [];
    $DBResults = $database->fetchAll($sql, $params);
    foreach ($DBResults as $accident) {
      $accident['id']                    = (int)$accident['id'];
      $accident['userid']                = (int)$accident['userid'];
      $accident['streamtopuserid']       = (int)$accident['streamtopuserid'];
      $accident['streamtoptype']         = (int)$accident['streamtoptype'];
      $accident['createtime']            = datetimeDBToISO8601($accident['createtime']);
      $accident['streamdatetime']        = datetimeDBToISO8601($accident['streamdatetime']);
      $accident['awaitingmoderation']    = $accident['awaitingmoderation'] == 1;

      $accident['pedestrian']            = $accident['pedestrian'] == 1;
      $accident['bicycle']               = $accident['bicycle'] == 1;
      $accident['scooter']               = $accident['scooter'] == 1;
      $accident['motorcycle']            = $accident['motorcycle'] == 1;
      $accident['car']                   = $accident['car'] == 1;
      $accident['taxi']                  = $accident['taxi'] == 1;
      $accident['emergencyvehicle']      = $accident['emergencyvehicle'] == 1;
      $accident['deliveryvan']           = $accident['deliveryvan'] == 1;
      $accident['tractor']               = $accident['tractor'] == 1;
      $accident['bus']                   = $accident['bus'] == 1;
      $accident['tram']                  = $accident['tram'] == 1;
      $accident['truck']                 = $accident['truck'] == 1;
      $accident['train']                 = $accident['train'] == 1;
      $accident['wheelchair']            = $accident['wheelchair'] == 1;
      $accident['mopedcar']              = $accident['mopedcar'] == 1;
      $accident['transportationunknown'] = $accident['transportationunknown'] == 1;

      $accident['child']                 = $accident['child'] == 1;
      $accident['pet']                   = $accident['pet'] == 1;
      $accident['alcohol']               = $accident['alcohol'] == 1;
      $accident['hitrun']                = $accident['hitrun'] == 1;
      $accident['trafficjam']            = $accident['trafficjam'] == 1;
      $accident['tree']                  = $accident['tree'] == 1;

      $ids[] = $accident['id'];
      $accidents[] = $accident;
    }

    if (count($accidents) > 0){
      $params = [];
      $sqlModerated = '';
      if ($moderationsList) {
        // In the moderation queue all articles are shown
      } else if ($id === null) { // Individual pages are always shown and *not* moderated. Needed
        $sqlModerated = $user->isModerator()? '':  ' AND ((ar.awaitingmoderation=0) || (ar.userid=:useridModeration)) ';
        if ($sqlModerated) $params[':useridModeration'] = $user->id;
      }

      $commaArrays = implode (", ", $ids);
      $sqlArticles = <<<SQL
SELECT
  ar.id,
  ar.userid,
  ar.awaitingmoderation,
  ar.accidentid,
  ar.title,
  ar.text,
  ar.createtime,
  ar.publishedtime,
  ar.streamdatetime,
  ar.sitename,
  ar.url,
  ar.urlimage,
  CONCAT(u.firstname, ' ', u.lastname) AS user 
FROM articles ar
JOIN users u on u.id = ar.userid
WHERE ar.accidentid IN ($commaArrays)
 $sqlModerated
ORDER BY ar.streamdatetime DESC
SQL;

      $DBResults = $database->fetchAll($sqlArticles, $params);
      foreach ($DBResults as $article) {
        $article['id']                 = (int)$article['id'];
        $article['userid']             = (int)$article['userid'];
        $article['awaitingmoderation'] = $article['awaitingmoderation'] == 1;
        $article['accidentid']         = (int)$article['accidentid'];
        $accident['createtime']        = datetimeDBToISO8601($accident['createtime']);
        $accident['publishedtime']     = datetimeDBToISO8601($accident['publishedtime']);
        $accident['streamdatetime']    = datetimeDBToISO8601($accident['streamdatetime']);
        // JD NOTE: Do not sanitize strings. We handle escaping in JavaScript

        $articles[] = $article;
      }
    }

    $result = ['ok' => true, 'accidents' => $accidents, 'articles' => $articles];
    if ($offset === 0) {
      $result['user'] = $user->info();
    }
  } catch (Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
//  $json = safe_json_encode($result); // JD Note: It looks like this is no longer required after converting the database to Unicode.
//  if ($json) echo $json;
//  else echo json_encode(['ok' => false, 'error' => json_last_error()]);
} // ====================
else if ($function === 'getuser') {
  try {
    $result = ['ok' => true, 'user' => $user->info()];
  } catch (Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  $json = json_encode($result);
  if ($json) echo $json;
  else echo json_encode(['ok' => false, 'error' => json_last_error()]);
} //==========
else if ($function === 'getPageMetaData'){
  try{
    $data       = json_decode(file_get_contents('php://input'), true);
    $url        = $data['url'];
    $newArticle = $data['newarticle'];

    function getFirstAvailableTag($tags){
      $result = '';
      foreach ($tags as $tag){
        if (isset($tag) && (! empty($tag)) && (strlen($tag) > strlen($result))) $result = $tag;
      }
      return $result;
    }

    $metaData     = getPageMediaMetaData($url);
    $ogTags       = $metaData['og'];
    $twitterTags  = $metaData['twitter'];
    $articleTags  = $metaData['article'];
    $itemPropTags = $metaData['itemprop'];
    $tagCount     = [
      'og'       =>count($metaData['og']),
      'twitter'  =>count($metaData['twitter']),
      'article'  =>count($metaData['article']),
      'itemprop' =>count($metaData['itemprop']),
      'other'    =>count($metaData['other']),
      ];

    // Decode HTML entities to normal text
    $media = [
      'url'            => getFirstAvailableTag([$ogTags['og:url'], $url]),
      'urlimage'       => getFirstAvailableTag([$ogTags['og:image']]),
      'title'          => html_entity_decode(strip_tags(getFirstAvailableTag([$ogTags['og:title'], $twitterTags['twitter:title']])),ENT_QUOTES),
      'description'    => html_entity_decode(strip_tags(getFirstAvailableTag([$ogTags['og:description'], $twitterTags['twitter:description'], $metaData['other']['description']])),ENT_QUOTES),
      'sitename'       => html_entity_decode(getFirstAvailableTag([$ogTags['og:site_name'], $metaData['other']['domain']]),ENT_QUOTES),
      'published_time' => getFirstAvailableTag([$ogTags['og:article:published_time'], $articleTags['article:published_time'], $itemPropTags['datePublished'], $articleTags['article:modified_time']]),
    ];

    // Replace http with https on image tags. Hart van Nederland sends unsecure links
    $media['urlimage'] = str_replace('http://', 'https://', $media['urlimage']);
    if (substr($media['urlimage'], 0, 1) === '/') {
      $parse = parse_url($media['url']);
      $media['urlimage'] = 'https://' . $parse['host'] . $media['urlimage'];
    }

    // Plan C if no other info available: Use H1 for title. Description for description
    if (($media['title']          === '') && (isset($metaData['other']['h1'])))          $media['title']          = $metaData['other']['h1'];
    if (($media['description']    === '') && (isset($metaData['other']['description']))) $media['description']    = $metaData['other']['description'];
    if (($media['published_time'] === '') && (isset($metaData['other']['time'])))        $media['published_time'] = $metaData['other']['time'];

    // Check if new article url already in database.
    $urlExists = false;
    if ($newArticle){
      $urlExists = urlExists($database, $media['url']);
    }

    $result = ['ok' => true, 'media' => $media, 'tagcount' => $tagCount, 'urlexists' => $urlExists];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} //==========
else if ($function === 'saveArticleAccident'){
  try {
    $data                         = json_decode(file_get_contents('php://input'), true);
    $article                      = $data['article'];
    $accident                     = $data['accident'];
    $saveArticle                  = $data['savearticle'];
    $saveAccident                 = $data['saveaccident'];
    $isNewAccident                = (! isset($accident['id'])) || ($accident['id'] <= 0);
    $moderationRequired           = ! $user->isModerator();
    $accidentIsAwaitingModeration = $moderationRequired && $isNewAccident;
    $articleIsAwaitingModeration  = $moderationRequired && (! $accidentIsAwaitingModeration);

    // Check if new article url already in database.
    if ($saveArticle && ($article['id'] < 1)){
      $exists = urlExists($database, $article['url']);
      if ($exists) throw new Exception("<a href='/{$exists['accidentid']}}' style='text-decoration: underline;'>Er is al een ongeluk met deze link</a>", 1);
    }


    if ($saveAccident){
      if (! $isNewAccident){
        // Update existing accident

        // We don't set awaitingmoderation for updates because it is unfriendly for helpers. We may need to come back on this policy if it is misused.
        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
        $sql = <<<SQL
    UPDATE accidents SET
      updatetime            = current_timestamp,
      streamdatetime        = current_timestamp,
      streamtoptype         = 1, 
      streamtopuserid       = :userid,
      title                 = :title,
      text                  = :text,
      date                  = :date,
      personsdead           = :personsdead,
      personsinjured        = :personsinjured,
      pedestrian            = :pedestrian,
      wheelchair            = :wheelchair,
      mopedcar              = :mopedcar,
      bicycle               = :bicycle,
      scooter               = :scooter,
      motorcycle            = :motorcycle,
      car                   = :car,
      taxi                  = :taxi,
      emergencyvehicle      = :emergencyvehicle,
      deliveryvan           = :deliveryvan,
      tractor               = :tractor,
      bus                   = :bus,
      tram                  = :tram,
      truck                 = :truck,
      train                 = :train,
      transportationunknown = :transportationunknown,
      child                 = :child,
      pet                   = :pet,
      alcohol               = :alcohol,
      hitrun                = :hitrun,
      trafficjam            = :trafficjam,
      tree                  = :tree
    WHERE id=:id $sqlANDOwnOnly
SQL;
        $params = array(
          ':id'                    => $accident['id'],
          ':userid'                => $user->id,
          ':title'                 => $accident['title'],
          ':text'                  => $accident['text'],
          ':date'                  => $accident['date'],
          ':personsdead'           => ($accident['personsdead']    == '')? null : $accident['personsdead'],
          ':personsinjured'        => ($accident['personsinjured'] == '')? null : $accident['personsinjured'],
          ':pedestrian'            => $accident['pedestrian'],
          ':wheelchair'            => $accident['wheelchair'],
          ':mopedcar'              => $accident['mopedcar'],
          ':bicycle'               => $accident['bicycle'],
          ':scooter'               => $accident['scooter'],
          ':motorcycle'            => $accident['motorcycle'],
          ':car'                   => $accident['car'],
          ':taxi'                  => $accident['taxi'],
          ':emergencyvehicle'      => $accident['emergencyvehicle'],
          ':deliveryvan'           => $accident['deliveryvan'],
          ':tractor'               => $accident['tractor'],
          ':bus'                   => $accident['bus'],
          ':tram'                  => $accident['tram'],
          ':truck'                 => $accident['truck'],
          ':train'                 => $accident['train'],
          ':transportationunknown' => $accident['transportationunknown'],
          ':child'                 => $accident['child'],
          ':pet'                   => $accident['pet'],
          ':alcohol'               => $accident['alcohol'],
          ':hitrun'                => $accident['hitrun'],
          ':trafficjam'            => $accident['trafficjam'],
          ':tree'                  => $accident['tree'],
        );
        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        $database->execute($sql, $params, true);
        if ($database->rowCount === 0) throw new Exception('Helpers kunnen alleen hun eigen ongelukken updaten. Sorry.');
      } else {
        // New accident

        $sql = <<<SQL
    INSERT INTO accidents (userid, awaitingmoderation, title, text, date, personsdead, personsinjured,                           
                           pedestrian, bicycle, scooter, motorcycle, car, taxi, emergencyvehicle, deliveryvan, tractor, bus, tram, truck, train, wheelchair, mopedcar, transportationunknown,
                           child, pet, alcohol, hitrun, trafficjam, tree)
    VALUES (:userid, :awaitingmoderation, :title, :text, :date, :personsdead, :personsinjured, 
            :pedestrian, :bicycle, :scooter, :motorcycle, :car, :taxi, :emergencyvehicle, :deliveryvan, :tractor, :bus, :tram, :truck, :train, :wheelchair, :mopedcar, :transportationunknown,
            :child, :pet, :alcohol, :hitrun, :trafficjam, :tree);
SQL;

        $params = array(
          ':userid'                => $user->id,
          ':awaitingmoderation'    => $moderationRequired,
          ':title'                 => $accident['title'],
          ':text'                  => $accident['text'],
          ':date'                  => $accident['date'],
          ':personsdead'           => ($accident['personsdead']    == '')? null : $accident['personsdead'],
          ':personsinjured'        => ($accident['personsinjured'] == '')? null : $accident['personsinjured'],
          ':pedestrian'            => $accident['pedestrian'],
          ':bicycle'               => $accident['bicycle'],
          ':scooter'               => $accident['scooter'],
          ':motorcycle'            => $accident['motorcycle'],
          ':car'                   => $accident['car'],
          ':taxi'                  => $accident['taxi'],
          ':emergencyvehicle'      => $accident['emergencyvehicle'],
          ':deliveryvan'           => $accident['deliveryvan'],
          ':tractor'               => $accident['tractor'],
          ':bus'                   => $accident['bus'],
          ':tram'                  => $accident['tram'],
          ':truck'                 => $accident['truck'],
          ':train'                 => $accident['train'],
          ':wheelchair'            => $accident['wheelchair'],
          ':mopedcar'              => $accident['mopedcar'],
          ':transportationunknown' => $accident['transportationunknown'],
          ':child'                 => $accident['child'],
          ':pet'                   => $accident['pet'],
          ':alcohol'               => $accident['alcohol'],
          ':hitrun'                => $accident['hitrun'],
          ':trafficjam'            => $accident['trafficjam'],
          ':tree'                  => $accident['tree'],
        );
        $dbresult = $database->execute($sql, $params);
        $accident['id'] = (int)$database->lastInsertID();
      }
    }

    if ($saveArticle){
      if ($article['id'] > 0){
        // Update article

        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';

        $sql = <<<SQL
    UPDATE articles SET
      accidentid  = :accidentid,
      url         = :url,
      title       = :title,
      text        = :text,
      publishedtime = :date,
      sitename    = :sitename,
      urlimage    = :urlimage
      WHERE id=:id $sqlANDOwnOnly
SQL;
        $params = [
          ':accidentid'  => $accident['id'],
          ':url'         => $article['url'],
          ':title'       => $article['title'],
          ':text'        => $article['text'],
          ':date'        => $article['date'],
          ':sitename'    => $article['sitename'],
          ':urlimage'    => $article['urlimage'],
          ':id'          => $article['id'],
        ];

        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        $database->execute($sql, $params, true);
        if ($database->rowCount === 0) throw new Exception('Kan artikel niet updaten.');

      } else {
        // New article

        $sql = <<<SQL
    INSERT INTO articles (userid, awaitingmoderation, accidentid, url, title, text, publishedtime, sitename, urlimage)
    VALUES (:userid, :awaitingmoderation, :accidentid, :url, :title, :text, :date, :sitename, :urlimage);
SQL;
        // Article moderation is only required if the accident is not awaiting moderation
        $params = array(
          ':userid'             => $user->id,
          ':awaitingmoderation' => $articleIsAwaitingModeration,
          ':accidentid'         => $accident['id'],
          ':url'                => $article['url'],
          ':title'              => $article['title'],
          ':text'               => $article['text'],
          ':sitename'           => $article['sitename'],
          ':date'               => $article['date'],
          ':urlimage'           => $article['urlimage']);

        $database->execute($sql, $params);
        $article['id'] = $database->lastInsertID();

        if (! $saveAccident){
          // New artikel
          // Update accident streamtype
          $sql = <<<SQL
    UPDATE accidents SET
      updatetime      = current_timestamp,
      streamdatetime  = current_timestamp,
      streamtoptype   = 2, 
      streamtopuserid = :userid
    WHERE id=:id;
SQL;
          $params = array(
            ':id'             => $accident['id'],
            ':userid'         => $user->id,
          );
          $database->execute($sql, $params);
        }
      }
    }

    $result = ['ok' => true, 'accidentid' => $accident['id']];
    if ($saveArticle) $result['articleid']  = $article['id'];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'deleteArticle'){
  try{
    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM articles WHERE id=:id $sqlANDOwnOnly ;";
      $params = array(':id' => $id);
      if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new Exception('Kan artikel niet verwijderen.');
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'deleteAccident'){
  try{
    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM accidents WHERE id=:id $sqlANDOwnOnly ;";
      $params = array(':id' => $id);
      if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new Exception('Alleen moderatoren mogen ongelukken verwijderen. Sorry.');
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'accidentToTopStream'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen ongelukken omhoog plaatsen. Sorry.');

    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql    = "UPDATE accidents SET streamdatetime=current_timestamp, streamtoptype=3, streamtopuserid=:userid WHERE id=:id;";
      $params = array(':id' => $id, ':userid' => $user->id);
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'accidentModerateOK'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen ongelukken modereren.');

    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql    = "UPDATE accidents SET awaitingmoderation=0 WHERE id=:id;";
      $params = array(':id' => $id);
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'articleModerateOK'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen artikelen modereren.');

    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql    = "UPDATE articles SET awaitingmoderation=0 WHERE id=:id;";
      $params = array(':id' => $id);
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'getstats'){
  try{
    $result = ['ok' => true,
      'statistics' => getStatistics($database),
      'user' => $user->info()
    ];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
}