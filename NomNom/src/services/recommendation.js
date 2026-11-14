import { apiRequest } from "./api";
import { useAuth } from "./auth";

// Record a post view for a logged-in account
export async function recordPostView(postId) {
  const { currentUser } = useAuth();
  const id = Number(postId);

  if (!currentUser.value || !Number.isInteger(id) || id < 1) {
    return;
  }

  return apiRequest("recommendation-history.php", {
    method: "POST",
    body: {
      type: "view",
      postId: id,
    },
  });
}

// Clean and record a search keyword for recommendation scoring
export async function recordSearch(query) {
  const { currentUser } = useAuth();

  const normalized = String(query).trim().replace(/\s+/g, " ").toLowerCase();

  const length = Array.from(normalized).length;

  // Skip guest activity and keywords outside the allowed length
  if (!currentUser.value || length < 2 || length > 100) {
    return;
  }

  return apiRequest("recommendation-history.php", {
    method: "POST",
    body: {
      type: "search",
      query: normalized,
    },
  });
}
