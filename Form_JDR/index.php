﻿<?php
if (session_status() != PHP_SESSION_ACTIVE)
    session_start();

# this is not to leak authotification information
# stored in config.php when pushing to github
if(!file_exists("php/config.php")){
    copy("php/config.php.default", "php/config.php");
}

require("php/config.php");


if(isset($_GET['action']) && $_GET['action'] === "login"){
	$params = array(
		'response_type' => 'code',
		'client_id' => CLIENT_ID,
		'redirect_uri' => REDIRECT_URI,
		'scope' => 'identify'
	);
	header('Location: https://discordapp.com/api/oauth2/authorize?' . http_build_query($params));
	die();	

}

if(isset($_GET['code'])){
	$post = array(
		"grant_type" => "authorization_code",
		"client_id" => CLIENT_ID,
		"client_secret" => CLIENT_SECRET,
		"redirect_uri" => REDIRECT_URI,
		"code" => $_GET['code']
	);
	$ch = curl_init("https://discord.com/api/oauth2/token");

	if (!curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4))
        die();
	if (!curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE))
        die();
    if (!curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)))
        die();

	$response = json_decode(curl_exec($ch));
    if (curl_errno($ch)) {
        // this would be your first hint that something went wrong
        die('Couldn\'t send request: ' . curl_error($ch));
    } else {
        // check the HTTP status code of the request
        $resultStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resultStatus == 200) {
            // everything went better than expected
        } else {
            // the request did not complete as expected. common errors are 4xx
            // (not found, bad request, etc.) and 5xx (usually concerning
            // errors/exceptions in the remote script execution)
    
            die('Request failed: HTTP status code: ' . $resultStatus);
        }
    }    
    $f = fopen("log.txt", 'a');
    fwrite($f, curl_error($ch) . "\n");
    fclose($f);
	print_r($response);
	$token = $response->access_token;
    
	$_SESSION['access_token'] = $token;
	header('Location: /');
}

if(isset($_SESSION['access_token'])){
	$header[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
	$ch = curl_init("https://discord.com/api/users/@me");
	curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	$response = json_decode(curl_exec($ch));
    $user_id = $response->id;
	$pseudo = $response->username . '#' . $response->discriminator;
	$avatar_url = 'https://cdn.discordapp.com/avatars/' . $response->id . '/' . $response->avatar . '.png' . '?size=32';


}

if (isset($_GET['webhook']))
    $_SESSION['webhook'] = $_GET['webhook'];

$emot_twitch = ' <:custom_emoji_name:434370263518412820> ';
$emot_roll20 = ' <:custom_emoji_name:493783713243725844> ';
$emot_discord = ' <:custom_emoji_name:434370093627998208> ';
$emot_autre = ' :space_invader: ';

?>


<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <title> Le formulaire de partie </title>
    <meta charset="utf8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="css/master.css">
    <link rel="stylesheet" href="css/styleDark.css">
    
    <link rel="icon" type="image/png" href="https://cdn.discordapp.com/attachments/457233258661281793/458727800048713728/dae-cmd.png">

    <script src="js/saveDescription.js"></script>
    <script src="js/updateSliderText.js"></script>
    <script src="js/durationSelect.js"></script>
    <script src="js/modeSwitch.js"></script>

    <!--On utilise un script externe pour le slider et le calendrier de choix de date et durée-->
    <script src="js/nouislider.js"></script> <!--Pour le slider du nombre de joueurs-->
    <link rel="stylesheet" href="css/nouislider.css">

    <script src="js/tail.datetime.js"></script><!-- Pour le calendrier du choix de la date-->
    <script src="js/tail.datetime-fr.js"></script>
    <link type="text/css" rel="stylesheet" href="css/tail.datetime-default.css">


</head>
<body onload="updateSliderText();durationSelect();"> 
<!--updateSliderText met à jour le texte situé sous le slider du nombre de joueur. durationSelect remplit le select des durées de parties (30m, 1h, ...) -->
    
<h1 class="titleCenter">Création de partie</h2>

    <form method=post action="cgi-bin/create_post.py" id="URform">

        <!-- Connexion discord -->              
        <label>Maître du jeu 👑</label>
        <?php
        if (isset($_SESSION['avatar_url']) and isset($_SESSION['username'])) {
            echo '<div>';
            echo "<img src=\"" . $_SESSION['avatar_url'] . "\"/>";      
            echo $_SESSION['username'];
            echo '</div>';
        } else
            echo '<div><input type="button" value="Me connecter" id="connexion" onclick="window.location.href=\'php/get_authorization_code.php\'"/></div>'
        ?>

        <!-- Button for changing color mode -->
        <label id="mode">Sombre 🌙</label>					
        <div>
            <label class="switch">
                <input type="checkbox" onclick="chgMode()">
                <span class="slider round"></span>
            </label>
        </div>
            
        <label>Nombre de joueurs</label>
            <div id="range" style="color:black !important" aria-describedby="nbTxt">
                <script>
                    var range = document.getElementById('range');

                     noUiSlider.create(range, {
                        start: [1, 5],
                        step:1,
                        range: {
                            'min': 0,
                            'max': 8
                        },
                        padding:[1,1],
                        connect:true

                    });
                </script>
            </div>
        <small id="nbTxt">Moins de 5 joueurs</small>


            
        <label> Type </label>             
        <select name="jdr_type" id="type" required>
            <option value="" disabled hidden selected></option> <!--Cette "option" force l'utilisateur à sélectionner une option-->
            <option> Initiation </option>
            <option> One shoot </option>
            <option> Scénario </option>
            <option> Campagne </option>
        </select>   
                 

            
        <label>Date 📅 et heure ⌚</label>
                
        <input autocomplete="off" id="date" name="jdr_date" type="text" class="tail-datetime-field" required style="border-radius: 0px !important; height:40px; width:100%"/>

        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function(){
            tail.DateTime(".tail-datetime-field", { 
                dateFormat:"dd/mm/YYYY",
                timeFormat:"HH:ii",
                locale:"fr",
                timeSeconds:false,
                viewDecades:false,
                dateStart:new Date().toISOString().slice(0, 10)});
            });
        </script> <!--L'attribut required force un champ à être rempli pour envoyer le formulaire-->
                    
           
            
        <!-- Nom campagne -->         
        <label> Titre : </label>
        <input type="text" placeholder="nom de la campagne ou du scenario" autocomplete="off" name="jdr_title" id="titre" max="50"> 									
                

        <!-- Durée -->       
        <label> Durée ⏱ </label>      
        <select name="jdr_length" id="selectorTime" required>
            <option value="" disabled hidden selected></option>
        </select>									
                    
        <div></div> <!--Pour faire de la place entre Durée et Jdr-->
            
           
        <!-- Sélection du système jdr -->       
        <label>JDR 🎲</label>
               
        <select name ="jdr_system" id="system">
            <option hidden disabled selected value="">Liste des JdR proposés</option>
            <?php
                # Generates all the options from an xml file
                $systems = simplexml_load_file("data/jdr_systems.xml");
                foreach ($systems as $optgroup) {
                    echo '<optgroup label ="' . $optgroup['label'] .'">';
                    foreach ($optgroup as $option) {
                        echo '<option>' . $option . '</option>'; 
                    }
                    echo '</optgroup>';
                }
            ?>         
        </select>				
            
        <!-- JDR Hors liste -->    
        <label> JDR Hors liste 🎲</label>
        <input type="text" placeholder="nom du jeu si hors liste" name="jdr_system_other" id="system2" max="37"> 									
            
            
        <!-- Outils -->   
        <label> Outils 🛠 </label>
        <div class="right">
            <input name="platform" type="checkbox" value="<?=$emot_twitch?>"> Partie diffusée sur Twitch <img src="img/iconTwitch.png"><br>
            <input name="platform" type="checkbox" value="<?=$emot_roll20?>"> Partie jouée sur Roll20 <img src="img/iconRoll20.png"><br>
            <input name="platform" type="checkbox" value="<?=$emot_discord?>" checked> Partie jouée sur Discord <img src="img/iconDiscord.png"><br>
            <input name="platform" type="checkbox" value=":space_invader:"> Partie jouée sur Autre <img src="img/iconAutre.png"><br>	
        </div>

        <!-- PJ mineurs -->       
        <label>PJ mineur 👶</label>
        <div class="right">
            <input type="radio" name="jdr_pj"  value="0" checked> &nbspOui
            <input type="radio" name="jdr_pj"  value="1"> &nbspNon préférable 
          <!--  <input type="radio" name="jdr_pj"  value="2"> &nbspPréférable  -->
            <input type="radio" name="jdr_pj"  value="3"> &nbspNon recommandé
        </div>
            
        <!-- Description -->
            
        <label>Description (optionnelle) 📄</label>        
        <textarea maxlength="500" rows="5" name ="jdr_details" id="desc" style="resize: vertical;" oninput="save()"></textarea>	
             

        <div class="right">	
            <button type="reset" onclick="document.getElementById('range').noUiSlider.set([1,5]);">Réinitialiser 🔄</button>	
            <br><br>			
            <button type="submit" style="background-color:#169719;" name="submit" id="submit" onclick="alert('Votre formulaire a bien été pris en compte')"><b>Valider ✔</b></button>					
        </div>

        
        <span class="beta"><b>Attention cet outil est en beta-test</b><br>
        <a href="https://github.com/UnionRolistes/Web_Presentation" uk-icon="icon: github; ratio:1.5">GitHub</a></span>

    </form>
    
    <script src="js/record_form.js"></script>
</body>

<?php 
    include('php/footer.php');
?>
</html>