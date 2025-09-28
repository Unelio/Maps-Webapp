<body class="login-page">

  <main>
  <form action="/" method="post">
    <div>
      <i class="ico icon-user"></i>
      <input name="login" type="text" autofocus placeholder="Identifiant" size="10" maxlength="255"/>
    </div>
    <div>
      <i class="ico icon-lock"></i>
      <input name="password" type="password" placeholder="Mot de passe" size="10" maxlength="255"/>
    </div>
    <input type="submit" value="Valider" />
    <?php if($error !="" ) : echo "<p id=\"error\">".$error."</p>"; endif; ?>
    <p id="result"></p>
  </form>
  
  <script>
    const res = document.getElementById("result");
    
    function testGeoloc() {
      if (!("geolocation" in navigator)) {
        res.textContent = "❌ Pas de support de la géolocalisation";
        return;
      }
      
      let timeout = setTimeout(() => {
        res.textContent = "❌ Géolocalisation bloquée";
      }, 3000); // 3 secondes max
      
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          clearTimeout(timeout);
          //res.textContent = "✅ La géolocalisation est activée" +
            "(Lat: " + pos.coords.latitude.toFixed(4) +
            ", Lon: " + pos.coords.longitude.toFixed(4) + ")";
        },
        (err) => {
          clearTimeout(timeout);
          if (err.code === err.PERMISSION_DENIED) {
            res.textContent = "❌ Géolocalisation refusée par l'utilisateur";
          } else if (err.code === err.POSITION_UNAVAILABLE) {
            res.textContent = "⚠️ Position indisponible";
          } else if (err.code === err.TIMEOUT) {
            res.textContent = "⚠️ Timeout lors de la récupération de la position";
          } else {
            res.textContent = "⚠️ Erreur inconnue";
          }
        },
        { timeout: 2000 } // limite de la requête
      );
    }
    
    testGeoloc();
  </script>
  </main>

</body>