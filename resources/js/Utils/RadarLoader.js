
/**
 * Lazy load Radar SDK only when needed
 * This avoids loading the library on every page
 */

let radarPromise = null;
let cssLoaded = false;

export async function loadRadar() {
    // Return existing promise if already loading
    if (radarPromise) {
        return radarPromise;
    }

    // Return existing instance if already loaded
    if (window.radar) {
        return Promise.resolve(window.radar);
    }

    // Create new loading promise - load both JS and CSS
    radarPromise = Promise.all([
        import('radar-sdk-js'),
        loadRadarCSS()
    ])
        .then(([module]) => {
            const Radar = module.default;

            // Do not initialize here without key. Let consumption code handle initialization.
            // Radar.initialize('');

            // Make it globally available
            window.radar = Radar;

            return Radar;
        })
        .catch(error => {
            console.error('Failed to load Radar SDK:', error);
            radarPromise = null; // Reset so it can be retried
            throw error;
        });

    return radarPromise;
}

/**
 * Load Radar CSS dynamically by importing it
 */
async function loadRadarCSS() {
    if (cssLoaded) {
        return Promise.resolve();
    }

    try {
        // Import the CSS - Vite will handle bundling it properly
        await import('radar-sdk-js/dist/radar.css');
        cssLoaded = true;
    } catch (error) {
        console.error('Failed to load Radar CSS:', error);
        throw error;
    }
}

