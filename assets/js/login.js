(function () {
  const res = document.getElementById('result');

  function testGeoloc() {
    if (!res) {
      return;
    }

    if (!('geolocation' in navigator)) {
      res.textContent = '❌ Pas de support de la géolocalisation';
      return;
    }

    let timeout = setTimeout(() => {
      res.textContent = '❌ Géolocalisation bloquée';
    }, 3000);

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        clearTimeout(timeout);
        void pos;
      },
      (err) => {
        clearTimeout(timeout);
        if (err.code === err.PERMISSION_DENIED) {
          res.textContent = '❌ Géolocalisation refusée par l\'utilisateur';
        } else if (err.code === err.POSITION_UNAVAILABLE) {
          res.textContent = '⚠️ Position indisponible';
        } else if (err.code === err.TIMEOUT) {
          res.textContent = '⚠️ Timeout lors de la récupération de la position';
        } else {
          res.textContent = '⚠️ Erreur inconnue';
        }
      },
      { timeout: 2000 }
    );
  }

  testGeoloc();
})();