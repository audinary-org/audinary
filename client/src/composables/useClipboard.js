/**
 * Composable for clipboard operations with fallback support
 */
export function useClipboard() {
  /**
   * Copy text to clipboard with multiple fallback strategies
   * @param {string} text - The text to copy
   * @param {Object} options - Optional configuration
   * @param {Function} options.onSuccess - Callback for successful copy
   * @param {Function} options.onError - Callback for copy failure
   * @param {boolean} options.showAlert - Whether to show alert as last resort
   * @returns {Promise<boolean>} - True if copy was successful
   */
  async function copyToClipboard(text, options = {}) {
    const {
      onSuccess = () => {},
      onError = (error) => console.error("Failed to copy:", error),
      showAlert = true,
    } = options;

    // Validate input
    if (!text || typeof text !== "string") {
      const error = new Error("Invalid text provided for clipboard operation");
      onError(error);
      return false;
    }

    try {
      // Method 1: Modern Clipboard API (requires HTTPS or localhost)
      if (
        navigator.clipboard &&
        typeof navigator.clipboard.writeText === "function"
      ) {
        await navigator.clipboard.writeText(text);
        onSuccess();
        return true;
      }

      // Method 2: Legacy document.execCommand approach
      const tempInput = document.createElement("input");
      tempInput.value = text;
      tempInput.style.position = "absolute";
      tempInput.style.left = "-9999px";
      tempInput.style.opacity = "0";
      tempInput.style.pointerEvents = "none";
      tempInput.setAttribute("readonly", "");
      tempInput.setAttribute("tabindex", "-1");

      document.body.appendChild(tempInput);

      try {
        tempInput.focus();
        tempInput.select();
        tempInput.setSelectionRange(0, 99999); // For mobile devices

        const successful = document.execCommand && document.execCommand("copy");

        if (successful) {
          onSuccess();
          return true;
        } else {
          throw new Error("execCommand copy failed");
        }
      } finally {
        // Always clean up the temporary element
        document.body.removeChild(tempInput);
      }
    } catch (error) {
      onError(error);

      // Method 3: Last resort - show alert with text to copy
      if (showAlert) {
        alert(`Copy this text: ${text}`);
      }

      return false;
    }
  }

  /**
   * Check if clipboard operations are supported
   * @returns {boolean} - True if clipboard API is available
   */
  function isClipboardSupported() {
    return (
      !!(navigator.clipboard && navigator.clipboard.writeText) ||
      !!(
        document.queryCommandSupported && document.queryCommandSupported("copy")
      )
    );
  }

  /**
   * Get the preferred clipboard method
   * @returns {string} - 'modern', 'legacy', or 'none'
   */
  function getClipboardMethod() {
    if (
      navigator.clipboard &&
      typeof navigator.clipboard.writeText === "function"
    ) {
      return "modern";
    } else if (
      document.queryCommandSupported &&
      document.queryCommandSupported("copy")
    ) {
      return "legacy";
    } else {
      return "none";
    }
  }

  return {
    copyToClipboard,
    isClipboardSupported,
    getClipboardMethod,
  };
}
