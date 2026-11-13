/**
 * src/utils/geo.js
 *
 * Geospatial helpers (post-backend migration):
 *   - formatDistance()  — human-readable distance string
 *   - geocodeAddress()  — forward geocoding via OpenStreetMap Nominatim
 *
 */

/**
 * Formats a raw distance number for UI display.
 * e.g. 0.4 -> "400 m away", 1.2 -> "1.2 km away"
 *
 * @param {number|null|undefined} km
 * @returns {string}
 */
export function formatDistance(km) {
  if (km === null || km === undefined) return "";
  if (km < 1) {
    return `${Math.round(km * 1000)} m away`;
  }
  return `${km.toFixed(1)} km away`;
}

/**
 * Forward geocoding — convert a text address into { lat, lng }.
 * Uses OpenStreetMap's Nominatim service (free, no API key).
 *
 * Nominatim usage policy:
 *   - Max 1 request per second
 *   - Must send a User-Agent or Referer (browser does this automatically)
 *   - No bulk geocoding
 * See: https://operations.osmfoundation.org/policies/nominatim/
 *
 * @param {string} address - Free-text address
 * @param {AbortSignal} [signal] - Optional AbortSignal to cancel the request
 * @returns {Promise<{ lat: number, lng: number, displayName: string } | null>}
 */
export async function geocodeAddress(address, signal) {
  const query = String(address ?? "").trim();

  if (!query) return null;
  if (query.length < 5) return null;

  const url =
    "https://nominatim.openstreetmap.org/search?" +
    new URLSearchParams({
      q: query,
      format: "jsonv2",
      limit: "1",
      addressdetails: "0",
    }).toString();

  const response = await fetch(url, {
    signal,
    headers: {
      Accept: "application/json",
    },
  });

  if (!response.ok) {
    throw new Error("Address lookup failed. Please try again.");
  }

  const data = await response.json();

  if (!Array.isArray(data) || data.length === 0) {
    return null; // no match
  }

  const hit = data[0];
  const lat = Number(hit.lat);
  const lng = Number(hit.lon);

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }

  return {
    lat,
    lng,
    displayName: hit.display_name || "",
  };
}