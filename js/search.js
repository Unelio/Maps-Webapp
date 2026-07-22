(function () {
  const form = document.getElementById('schSearchForm');
  const input = document.getElementById('schSearchInput');
  const overlay = document.getElementById('schOverlay');
  const status = document.getElementById('schSearchStatus');

  if (!input || !overlay || !form) return;

  // Affiche un message temporaire sous le champ de recherche
  function setStatus(message, isError = false) {
    if (!status) return;
    status.textContent = message;
    status.style.width = '80%';
    status.style.maxWidth = '400px';
    status.style.margin = '10px 0 0';
    status.style.textAlign = 'center';
    status.style.fontSize = '14px';
    status.style.minHeight = '1.2em';
    status.style.color = isError ? '#b00020' : '#333';
  }

  // Géocodage gratuit sans clé via un endpoint local qui interroge Nominatim.
  async function geocodeWithNominatim(query) {
    const url = new URL('/inc/search.php', window.location.origin);
    url.searchParams.set('format', 'jsonv2');
    url.searchParams.set('q', query);
    url.searchParams.set('limit', '1');

    const response = await fetch(url.toString(), {
      headers: {
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      const payload = await response.json().catch(() => ({}));
      throw new Error(payload && payload.message ? payload.message : 'Recherche indisponible');
    }

    const payload = await response.json();
    if (!payload || payload.found === false) {
      return null;
    }

    return {
      lat: Number(payload.lat),
      lng: Number(payload.lng),
      label: payload.label || query,
    };
  }

  // Point d'entrée unique pour la recherche d'adresse.
  async function geocode(query) {
    return geocodeWithNominatim(query);
  }

  // Envoie les coordonnées à l'iframe pour placer le point sur la carte.
  function flyToResult(result) {
    if (!window.mapBridge || typeof window.mapBridge.postToIframe !== 'function') {
      throw new Error('Carte indisponible');
    }

    window.mapBridge.postToIframe({
      type: 'search',
      lat: result.lat,
      lng: result.lng,
      label: result.label,
    });
  }

  // Lance la recherche quand l'utilisateur valide le champ
  async function searchAddress() {
    const query = input.value.trim();

    if (!query) {
      setStatus('Saisis une adresse.', true);
      return;
    }

    overlay.classList.remove('show');
    input.blur();

    try {
      setStatus('Recherche en cours...');
      const result = await geocode(query);

      if (!result || Number.isNaN(result.lat) || Number.isNaN(result.lng)) {
        setStatus('Adresse introuvable.', true);
        return;
      }

      flyToResult(result);
      setStatus('');
    } catch (error) {
      setStatus(error && error.message ? error.message : 'Erreur de recherche.', true);
    }
  }

  // Soumission du formulaire = lancer la recherche
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    searchAddress();
  });

  // Filet de sécurité si le navigateur ne déclenche pas le submit correctement
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      searchAddress();
    }
  });

  // Nettoie le message d'état dès que l'utilisateur retape
  input.addEventListener('input', function () {
    if (status && status.textContent) {
      setStatus('');
    }
  });
})();