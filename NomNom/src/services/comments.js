import { apiRequest } from "./api";
import { useAuth } from "./auth";

function resolveImageUrl(url) {
  if (typeof url !== "string" || url === "") return "";

  // If it's a nomnom-api or api path, prefix it with the app base
  // so the URL is absolute from the domain root.
  if (url.startsWith("api/") || url.startsWith("nomnom-api/")) {
    // Normalize old api/ prefix to nomnom-api/
    const cleaned = url.startsWith("api/")
      ? url.replace(/^api\//, "nomnom-api/")
      : url;

    // Ensure the leading slash so it's absolute
    const base = import.meta.env.BASE_URL.endsWith("/")
      ? import.meta.env.BASE_URL
      : import.meta.env.BASE_URL + "/";

    return `${base}${cleaned}`;
  }

  // External URLs (http, https, data:) pass through unchanged
  return url;
}

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

export async function getComments(postId) {
  const data = await apiRequest(
    `comments.php?post_id=${encodeURIComponent(postId)}`,
  );

  return normalizeComments(data);
}

async function writeComments(endpoint, method, body) {
  const { initAuth, isLoggedIn } = useAuth();
  await initAuth();

  if (!isLoggedIn.value) {
    throw new Error("Please log in to comment.");
  }

  const data = await apiRequest(endpoint, { method, body });
  return normalizeComments(data);
}

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

export function updateComment(id, text) {
  return writeComments(`comments.php?id=${encodeURIComponent(id)}`, "PUT", {
    text,
  });
}

export function deleteComment(id) {
  return writeComments(`comments.php?id=${encodeURIComponent(id)}`, "DELETE");
}
