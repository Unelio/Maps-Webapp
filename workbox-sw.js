/*
 * Wrapper Workbox pour charger les modules à la demande dans le service worker
 */
(function () {
	"use strict";

	// Déclenche l'initialisation Workbox si la signature attendue est présente
	try {
		self["workbox:sw:5.1.2"] && _();
	} catch (error) {}

	// Correspondance entre le nom logique d'un module Workbox et son paquet réel
	const moduleAliases = {
		backgroundSync: "background-sync",
		broadcastUpdate: "broadcast-update",
		cacheableResponse: "cacheable-response",
		core: "core",
		expiration: "expiration",
		googleAnalytics: "offline-ga",
		navigationPreload: "navigation-preload",
		precaching: "precaching",
		rangeRequests: "range-requests",
		routing: "routing",
		strategies: "strategies",
		streams: "streams",
	};

	// Expose un objet workbox qui charge dynamiquement les modules au premier accès
	self.workbox = new (class {
		constructor() {
			// Contient les modules déjà chargés
			this.v = {};

			// Paramètres de configuration du loader
			this.t = {
				debug: "localhost" === self.location.hostname,
				modulePathPrefix: null,
				modulePathCb: null,
			};

			// En debug on charge les versions dev, sinon les versions prod
			this.s = this.t.debug ? "dev" : "prod";

			// Empêche de modifier la configuration après le premier chargement
			this.o = false;

			return new Proxy(this, {
				get(target, property) {
					// Si la propriété existe déjà sur l'instance, on la renvoie telle quelle
					if (target[property]) return target[property];

					// Sinon on tente de résoudre un alias Workbox connu
					const alias = moduleAliases[property];
					return alias && target.loadModule(`workbox-${alias}`), target[property];
				},
			});
		}

		// Permet de personnaliser le chargement avant le premier accès à un module
		setConfig(config = {}) {
			if (this.o) {
				throw new Error("Config must be set before accessing workbox.* modules");
			}

			Object.assign(this.t, config);
			this.s = this.t.debug ? "dev" : "prod";
		}

		// Charge un module Workbox via importScripts
		loadModule(moduleName) {
			const moduleUrl = this.i(moduleName);

			try {
				importScripts(moduleUrl);
				this.o = true;
			} catch (error) {
				console.error(
					`Unable to import module '${moduleName}' from '${moduleUrl}'.`
				);
				throw error;
			}
		}

		// Construit l'URL du module à charger
		i(moduleName) {
			if (this.t.modulePathCb) {
				return this.t.modulePathCb(moduleName, this.t.debug);
			}

			let segments = ["https://storage.googleapis.com/workbox-cdn/releases/5.1.2"];
			const fileName = `${moduleName}.${this.s}.js`;
			const prefix = this.t.modulePathPrefix;

			if (prefix) {
				segments = prefix.split("/");

				if ("" === segments[segments.length - 1]) {
					segments.splice(segments.length - 1, 1);
				}
			}

			segments.push(fileName);
			return segments.join("/");
		}
	})();
})();
