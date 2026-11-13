/**
 * Scrolls to the first element matching one of the given selectors
 * and optionally focuses it.
 *
 * @param {HTMLElement} container - The form element
 * @param {string[]} selectors - CSS selectors to search, in priority order
 * @param {Object} options
 * @param {boolean} options.focus - Whether to focus the element (default: true)
 * @param {number} options.offset - Offset from top (default: 100px for navbar)
 */
export function scrollToFirstError(container, selectors, options = {}) {
  if (!container) return;

  const { focus = true, offset = 100 } = options;

  // Search for the first element matching any of the selectors
  let target = null;
  for (const selector of selectors) {
    target = container.querySelector(selector);
    if (target) break;
  }

  if (!target) return;

  // Compute scroll position with offset for fixed navbar
  const rect = target.getBoundingClientRect();
  const scrollTop = window.scrollY + rect.top - offset;

  window.scrollTo({
    top: Math.max(0, scrollTop),
    behavior: 'smooth',
  });

  // Focus the element (works for inputs, textareas, selects)
  // For non-focusable elements, add tabindex="-1" or wrap in a focusable div
  if (focus && typeof target.focus === 'function') {
    // preventScroll: true stops the browser from doing its own scroll
    setTimeout(() => {
      try {
        target.focus({ preventScroll: true });
      } catch (e) {
        target.focus();
      }
    }, 300);
  }

  // Add a temporary highlight animation
  target.classList.add('scroll-error-highlight');
  setTimeout(() => target.classList.remove('scroll-error-highlight'), 2000);
}