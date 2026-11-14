// Shared API requests, session cookies, and error handling

const API_BASE = `${import.meta.env.BASE_URL}nomnom-api/`;

let csrfToken = "";

// Keep the latest security token for requests that change data
export function setCsrfToken(token) {
  csrfToken = token || "";
}

// Send JSON or uploaded files to the PHP backend
export async function apiRequest(
  endpoint,
  { method = "GET", body, signal } = {},
) {
  const isFormData = body instanceof FormData;
  const headers = {
    Accept: "application/json",
  };

  if (body !== undefined && !isFormData) {
    headers["Content-Type"] = "application/json";
  }

  // Attach the security token to write requests
  if (method !== "GET" && csrfToken) {
    headers["X-CSRF-Token"] = csrfToken;
  }

  let response;

  // Include session cookies and allow callers to cancel requests
  try {
    response = await fetch(`${API_BASE}${endpoint}`, {
      method,
      headers,
      signal,
      credentials: "same-origin",
      body:
        body === undefined
          ? undefined
          : isFormData
            ? body
            : JSON.stringify(body),
    });
  } catch (error) {
    if (error.name === "AbortError") throw error;

    throw new Error(
      "Cannot reach the server. Check that XAMPP and Vite are running.",
    );
  }

  // Read the JSON response and report server errors to the caller
  let data;

  try {
    data = await response.json();
  } catch {
    throw new Error(
      "The server returned an unexpected response. Check the PHP endpoint and Apache logs.",
    );
  }

  if (!response.ok || data.ok === false) {
    const error = new Error(data.message || "The request failed.");
    error.status = response.status;
    throw error;
  }

  return data;
}
