<?php

function getHTMLBeginMain($pageTitle='', $head='', $initFunction='', $showAccidentMenu=false){
  global $VERSION;

  $title = 'Het Ongeluk';
  if ($pageTitle !== '') $title = $pageTitle . ' | ' . $title;
  $initScript = ($initFunction !== '')? "<script>document.addEventListener('DOMContentLoaded', $initFunction);</script>" : '';
  $navigation = getNavigation();

  if (! cookiesApproved()) {
    $cookieWarning = <<<HTML
    <div id="cookieWarning" class="flexRow">
      <div>Deze website gebruikt cookies. 
        <a href="/overdezesite/#cookieInfo" style="text-decoration: underline; color: inherit;">Meer info.</a>
      </div>
      <div class="button" onclick="acceptCookies();">Akkoord</div>
    </div>
HTML;
  } else $cookieWarning = '';

  $mainMenuItems = '';
  if ($showAccidentMenu) $mainMenuItems = <<<HTML
  <div id="buttonSearch" class="menuButton buttonSearch" onclick="toggleSearchBar(event);"></div>
  <div id="buttonNewArticle" class="menuButton buttonAdd" onclick="showEditAccidentForm();"></div>
HTML;

  return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<link href="https://fonts.googleapis.com/css?family=Lora|Montserrat" rel="stylesheet">
<link href="/main.css?v=$VERSION" rel="stylesheet" type="text/css">
<link rel="shortcut icon" type="image/png" href="/images/hetongeluk.png">
$head
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta charset="utf-8">
<title>$title</title>
<script src="/scripts/tippy.all.min.js"></script>
<script src="/js/utils.js?v=$VERSION"></script>

$initScript

</head>
<body>
$navigation

<div id="pageBar">
  <div id="topBar">
    <span class="menuButton bgMenu" onclick="toggleNavigation(event);"></span>
  
    <div class="headerMain pageTitle"><a href="/">Het Ongeluk</a></div>
   
    <div class="topButtons">
      $mainMenuItems
      <div id="loginButton" onclick="loginClick(event);">
        <div id="buttonPerson" class="menuButton bgPerson"></div>
        <div id="loginText">Log in</div>
        <div id="loginName" class="hideOnMobile"></div>
      </div>
  
      <div id="menuPerson" class="buttonPopupMenu">
        <div id="menuProfile" class="menuHeader"></div>
        <div id="menuLogin" onclick="showLoginForm();">Log in</div> 
        <div id="menuLogout" style="display: none;" onclick="logOut();">Log uit</div>
      </div>
    </div>
  </div>
  
  <div id="searchBar">
     <input id="searchText" type="search" placeholder="Zoek" onkeyup="startSearchKey(event);" autocomplete="off">
     <div class="button" onclick="startSearch(event)">Zoek</div>
     <div class="closeButton" onclick="toggleSearchBar();"></div>  
  </div>      

</div>

$cookieWarning

HTML;
}

function getHTMLEnd($htmlEnd='', $flexFullPage=false){
  $htmlFlex = $flexFullPage? '</div>' : '';
  $forms    = getHTMLConfirm() . getLoginForm() . getFormEditAccident() . getFormEditPerson();
  return <<<HTML
    $htmlEnd 
    <div id="floatingMessage" class="floatingMessage" onclick="hideMessage();">
      <div id="messageCloseCross" class="popupCloseCross crossWhite"></div>
      <div id="messageText"></div>
    </div>
    <div style="clear: both;"></div>
    $htmlFlex
    $forms
</body>
HTML;
}

function getHTMLConfirm(){
  $formConfirm = <<<HTML
<div id="formConfirmOuter" class="popupOuter" style="z-index: 1000" onclick="closePopupForm();">
  <form id="formConfirm" class="floatingForm" onclick="event.stopPropagation();">

    <div id="confirmHeader" class="popupHeader">Bevestigen</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <div id="confirmText" class="textMessage"></div>

    <div class="popupFooter">
      <button id="buttonConfirmOK" class="button" type="submit" autofocus>OK</button>
      <button id="buttonConfirmCancel" class="button buttonGray" type="button" onclick="hideDiv('formConfirmOuter');">Annuleren</button>
    </div>    

  </form>
</div>
HTML;

  return $formConfirm;
}

function getNavigation(){

  global $VERSION;
  global $VERSION_DATE;

  return <<<HTML
<div id="navShadow" class="navShadow" onclick="closeNavigation()"></div>
<div id="navigation" onclick="closeNavigation();">
  <div class="navHeader">
    <div class="popupCloseCross" onclick="closeNavigation();"></div>
    <div class="navHeaderTop"><span class="pageTitle">Het Ongeluk</span></div>      
  </div>
  <div style="overflow-y: auto;">
  
    <div class="navigationSection">
      <div class="navigationSectionHeader">Ongelukken</div>
      <a href="/" class="navItem">Recente ongelukken</a>
      <a href="/stream" class="navItem">Ongelukken stream</a>
    </div>

    <div class="navigationSection">
      <div class="navigationSectionHeader">Overig</div>
      <a href="/statistieken/" class="navItem">Statistieken</a>
      <a href="/overdezesite/" class="navItem">Over deze site</a>
    </div>

    <div id="navigationAdmin" data-moderator>    
      <div class="navigationSectionHeader">Beheer</div>
  
      <div class="navigationSection">
        <a href="/beheer/gebruikers" class="navItem" data-admin>Gebruikers</a>
        <a href="/moderaties/" class="navItem">Moderaties</a>
        <a href="/beheer/exporteren/" class="navItem">Exporteer data</a>
      </div>      
    </div>
    
    <div class="navFooter">
     Versie $VERSION • $VERSION_DATE 
    </div>   
 
  </div>  
</div>
HTML;
}

function getLoginForm() {
  return <<<HTML
<div id="formLogin" class="popupOuter" onclick="closePopupForm();">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="return checkLogin();">

    <div class="popupHeader">Log in of registreer</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="spinnerLogin" class="spinner"></div>

    <label for="loginEmail">Email</label>
    <input id="loginEmail" class="popupInput" type="email" autocomplete="email">
    
    <div id="divFirstName" class="displayNone flexColumn">
      <label for="loginFirstName">Voornaam</label>
      <input id="loginFirstName" class="popupInput" autocomplete="given-name" type="text">
    </div>
  
    <div id="divLastName" class="displayNone flexColumn">
      <label for="loginLastName">Achternaam</label>
      <input id="loginLastName" class="popupInput" autocomplete="family-name" type="text">
    </div>
    
    <label for="loginPassword">Wachtwoord</label>
    <input id="loginPassword" class="popupInput" type="password" autocomplete="current-password">

    <div id="divPasswordConfirm" class="displayNone flexColumn">
      <label for="loginPasswordConfirm">Wachtwoord bevestigen</label>
      <input id="loginPasswordConfirm" class="popupInput" type="password" autocomplete="new-password">
    </div>   
    
    <label><input id="stayLoggedIn" type="checkbox" checked>Ingelogd blijven</label>

    <div id="loginError" class="formError"></div>

    <div class="popupFooter">
      <input id="buttonLogin" type="submit" class="button" style="margin-left: 0;" value="Log in">
      <input id="buttonRegistreer" type="button" class="button buttonGray" value="Registreer" onclick="checkRegistration();">

      <span onclick="loginForgotPassword()" style="margin-left: auto; text-decoration: underline; cursor: pointer;">Wachtwoord vergeten</span>
    </div>
    
  </form>
</div>  
HTML;
}

function getFormEditAccident(){
  return <<<HTML
<div id="formEditAccident" class="popupOuter" onclick="closePopupForm();">

  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader">Nieuw artikel toevoegen</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <div id="editArticleSection" class="flexColumn">
      <input id="articleIDHidden" type="hidden">
  
      <div class="labelDiv">
        <label for="editArticleUrl">Artikel link (URL)</label>
        <span class="iconTooltip" data-tippy-content="Kopieer de link uit de adresbalk van de webpagina met het artikel"></span>
      </div>

      <div style="display: flex;">
        <input id="editArticleUrl" class="popupInput" type="url" maxlength="1000" autocomplete="off">
        <div class="button buttonLine" onclick="getArticleMetaData();">Artikel ophalen</div>
      </div>
  
      <div id="spinnerMeta" class="spiderBackground">
        <div class="popupHeader">Tarantula is aan het werk...</div>
        <div><img src="/images/tarantula.jpg" style="height: 200px;"></div>
        <div id="tarantuleResults"></div> 
      </div>
  
      <label for="editArticleSiteName">Mediabron</label>
      <input id="editArticleSiteName" class="popupInput" type="text" maxlength="200" autocomplete="off" data-readonlyhelper>
     
      <label for="editArticleTitle">Titel</label>
      <input id="editArticleTitle" class="popupInput" type="text" maxlength="500" autocomplete="off" data-readonlyhelper>
  
      <label for="editArticleText">Tekst</label>
      <textarea id="editArticleText" maxlength="500" style="height: 50px; resize: vertical;" class="popupInput" autocomplete="off" data-readonlyhelper></textarea>
  
      <label for="editArticleUrlImage">Foto link (URL)</label>
      <input id="editArticleUrlImage" class="popupInput" type="url" maxlength="1000" autocomplete="off" data-readonlyhelper>
      
      <label for="editArticleDate">Publicatiedatum</label>
      <input id="editArticleDate" class="popupInput" type="date" autocomplete="off">
    </div>

    <div id="editAccidentSection" class="flexColumn">
      <div class="formSubHeader">Ongeluk</div>
     
      <input id="accidentIDHidden" type="hidden">
  
      <div data-hidehelper class="flexColumn">
        <label for="editAccidentTitle">Titel ongeluk</label> 
        <div style="display: flex;">
          <input id="editAccidentTitle" class="popupInput" type="text" maxlength="500" autocomplete="off" data-readonlyhelper>
          <span data-hideedit class="button buttonGray buttonLine" onclick="copyAccidentInfoFromArticle();" ">Zelfde als artikel</span>
        </div>
  
        <label for="editAccidentText">Tekst</label>
        <textarea id="editAccidentText" maxlength="500" style="height: 50px; resize: vertical;" class="popupInput" autocomplete="off" data-readonlyhelper></textarea>
      </div>        

      <div class="labelDiv">
        <label for="editAccidentDate">Datum ongeluk</label>
        <span class="iconTooltip" data-tippy-content="Vaak anders dan publicatiedatum artikel"></span>
      </div>
      <div style="display: flex;">
        <input id="editAccidentDate" class="popupInput" type="date" autocomplete="off">
        <span data-hideedit class="button buttonGray buttonLine" onclick="copyAccidentDateFromArticle();" ">Zelfde als artikel</span>
      </div>
          
      <div style="margin-top: 5px;">
        <div>Betrokken personen <span class="button buttonGray buttonLine" onclick="showEditPersonForm();">Persoon toevoegen</span></div>   
        <div id="editAccidentPersons"></div>
      </div>

      <div style="margin-top: 5px; display: none;">
        <div>Kenmerken</div>
        <div>
          <span id="editAccidentPet" class="menuButton bgPet" data-tippy-content="Dier(en)" onclick="changeAccidentInvolved(this, event);"></span>      
          <span id="editAccidentTrafficJam" class="menuButton bgTrafficJam" data-tippy-content="File/Hinder" onclick="changeAccidentInvolved(this, event);"></span>      
          <span id="editAccidentTree" class="menuButton bgTree" data-tippy-content="Boom/Paal" onclick="changeAccidentInvolved(this, event);"></span>
        </div>
      </div>
    </div>
            
    <div class="popupFooter">
      <input id="buttonSaveArticle" type="button" class="button" value="Opslaan" onclick="saveArticleAccident();">
      <input type="button" class="button buttonGray" value="Annuleren" onclick="closePopupForm();">
    </div>    
  </form>
  
</div>
HTML;
}

function getFormEditPerson(){
  return <<<HTML
<div id="formEditPerson" class="popupOuter" style="z-index: 501;" onclick="closeEditPersonForm();">

  <div class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editPersonHeader" class="popupHeader">Nieuw persoon toevoegen</div>
    <div class="popupCloseCross" onclick="closeEditPersonForm();"></div>

    <input id="personIDHidden" type="hidden">
    <input id="personAccidentIDHidden" type="hidden">

    <div style="margin-top: 5px;">
      <div>Vervoermiddel</div> 
      <div id="personTransportationButtons"></div>
    </div>
            
    <div style="margin-top: 5px;">
      <div>Letsel</div> 
      <div id="personHealthButtons"></div>
    </div>

    <div style="margin-top: 5px;">
      <div>Kenmerken</div> 
      <div>
        <span id="editPersonChild" class="menuButton bgChild" data-tippy-content="Kind" onclick="toggleMenuButton(this)"></span>            
      </div>
    </div>
            
    <div class="popupFooter">
      <input type="button" class="button" value="Opslaan en openblijven" onclick="savePerson(true);">
      <input type="button" class="button" value="Opslaan en sluiten" onclick="savePerson();">
      <input id="buttonCloseEditPerson" type="button" class="button buttonGray" value="Sluiten" onclick="closeEditPersonForm();">
      <input id="buttonDeletePerson" type="button" class="button buttonRed" value="Verwijderen" onclick="deletePerson();">
    </div>    
  </div>
  
</div>
HTML;
}

function getFormEditUser(){
  return
    <<<HTML
<div id="formEditUser" class="popupOuter" onclick="closePopupForm();">
  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader">Gebruiker bewerken</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <input id="userID" type="hidden">

    <label for="userEmail">Email</label>
    <input id="userEmail" class="inputForm" style="margin-bottom: 10px;" type="text"">
      
    <label for="userFirstName">Voornaam</label>
    <input id="userFirstName" class="inputForm" style="margin-bottom: 10px;" type="text"">
  
    <label for="userLastName">Achternaam</label>
    <input id="userLastName" class="inputForm" style="margin-bottom: 10px;" type="text"">

    <label for="userPermission">Permissie</label>
    <select id="userPermission">
      <option value="0">Helper (ongelukken worden gemodereerd)</option>
      <option value="2">Moderator (kan alle ongelukken bewerken)</option>
      <option value="1">Beheerder</option>
    </select>
    
    <div id="editUserError" class="formError"></div>
   
    <div class="popupFooter">
      <input type="button" class="button" value="Opslaan" onclick="saveUser();">
      <input type="button" class="button buttonGray" value="Annuleren" onclick="hideDiv('formEditUser');">
    </div>    
  </form>
</div>
HTML;
}


