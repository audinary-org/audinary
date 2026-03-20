import { defineStore } from "pinia";
import { ref } from "vue";

export const useAlertStore = defineStore("alert", () => {
  // State
  const alerts = ref([]);
  const nextId = ref(1);

  // Actions
  function addAlert(message, type = "info", duration = 5000) {
    const alert = {
      id: nextId.value++,
      message,
      type, // 'success', 'error', 'warning', 'info'
      timestamp: Date.now(),
    };

    alerts.value.push(alert);

    // Auto-remove after duration
    if (duration > 0) {
      setTimeout(() => {
        removeAlert(alert.id);
      }, duration);
    }

    return alert.id;
  }

  function addOrUpdateAlert(key, message, type = "info", duration = 5000) {
    // Check if an alert with this key already exists
    const existingAlert = alerts.value.find((alert) => alert.key === key);

    if (existingAlert) {
      // Update existing alert
      existingAlert.message = message;
      existingAlert.type = type;
      existingAlert.timestamp = Date.now();
      return existingAlert.id;
    } else {
      // Create new alert with key
      const alert = {
        id: nextId.value++,
        key, // Add custom key for identification
        message,
        type,
        timestamp: Date.now(),
      };

      alerts.value.push(alert);

      // Auto-remove after duration
      if (duration > 0) {
        setTimeout(() => {
          removeAlert(alert.id);
        }, duration);
      }

      return alert.id;
    }
  }

  function removeAlert(id) {
    const index = alerts.value.findIndex((alert) => alert.id === id);
    if (index > -1) {
      alerts.value.splice(index, 1);
    }
  }

  function removeAlertByKey(key) {
    const index = alerts.value.findIndex((alert) => alert.key === key);
    if (index > -1) {
      alerts.value.splice(index, 1);
    }
  }

  function findAlertByKey(key) {
    return alerts.value.find((alert) => alert.key === key);
  }

  function updateAlert(id, message) {
    const alert = alerts.value.find((alert) => alert.id === id);
    if (alert) {
      alert.message = message;
      alert.timestamp = Date.now();
    }
  }

  function clearAll() {
    alerts.value = [];
  }

  // Convenience methods
  function success(message, duration = 5000) {
    return addAlert(message, "success", duration);
  }

  function error(message, duration = 7000) {
    return addAlert(message, "error", duration);
  }

  function warning(message, duration = 6000) {
    return addAlert(message, "warning", duration);
  }

  function info(message, duration = 5000) {
    return addAlert(message, "info", duration);
  }

  return {
    // State
    alerts,

    // Actions
    addAlert,
    addOrUpdateAlert,
    removeAlert,
    removeAlertByKey,
    findAlertByKey,
    updateAlert,
    clearAll,
    success,
    error,
    warning,
    info,
  };
});
