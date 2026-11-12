// handle JSON requests, API errors, session cookies,
// and the security token used by our upcoming registration/login endpoints.

const API_BASE = `${import.meta.env.BASE_URL}api/`;

let csrfToken = "";

export function setCsrfToken(token) {
  csrfToken = token || "";
}

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

  if (method !== "GET" && csrfToken) {
    headers["X-CSRF-Token"] = csrfToken;
  }

  let response;

  try {
    response = await fetch(`${API_BASE}${endpoint}`, {
      method,
      headers,
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
