import { apiRequest } from "./api";
import { useAuth } from "./auth";

// Resolve uploaded image paths for the current app location
function resolveImageUrl(url) {
  if (typeof url !== "string" || url === "") return "";

  // Prefix backend image paths with the app base
  if (url.startsWith("api/") || url.startsWith("nomnom-api/")) {
    // Normalize old api/ prefix to nomnom-api/
    const cleaned = url.startsWith("api/")
      ? url.replace(/^api\//, "nomnom-api/")
      : url;

    // Keep a trailing slash before joining the image path
    const base = import.meta.env.BASE_URL.endsWith("/")
      ? import.meta.env.BASE_URL
      : import.meta.env.BASE_URL + "/";

    return `${base}${cleaned}`;
  }

  // External URLs (http, https, data:) pass through unchanged
  return url;
}

// Prepare comment avatars and attached image paths for display
function normalizeComments(data) {
  return data.comments.map((comment) => ({
    ...comment,
    userAvatar: comment.userAvatar.startsWith("api/")
      ? `${import.meta.env.BASE_URL}${comment.userAvatar}`
      : comment.userAvatar,
    userAvatar: resolveImageUrl(comment.userAvatar),
    images: comment.images.map(resolveImageUrl),
  }));
}

// Load comments for the selected post
export async function getComments(postId) {
  const data = await apiRequest(
    `comments.php?post_id=${encodeURIComponent(postId)}`,
  );

  return normalizeComments(data);
}

// Require login before creating, editing, or deleting a comment
async function writeComments(endpoint, method, body) {
  const { initAuth, isLoggedIn } = useAuth();
  await initAuth();

  if (!isLoggedIn.value) {
    throw new Error("Please log in to comment.");
  }

  const data = await apiRequest(endpoint, { method, body });
  return normalizeComments(data);
}

// Submit comment text and optional photos
export function createComment(postId, comment) {
  return writeComments(
    `comments.php?post_id=${encodeURIComponent(postId)}`,
    "POST",
    {
      text: comment.text,
      images: comment.images,
    },
  );
}

// Update comment text while the backend keeps existing photos
export function updateComment(id, text) {
  return writeComments(`comments.php?id=${encodeURIComponent(id)}`, "PUT", {
    text,
  });
}

// Delete an owned comment through the backend
export function deleteComment(id) {
  return writeComments(`comments.php?id=${encodeURIComponent(id)}`, "DELETE");
}
