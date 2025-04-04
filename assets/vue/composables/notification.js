import { useToast } from "primevue/usetoast"

export function useNotification() {
  const toast = useToast()

  const showSuccessNotification = (message) => {
    showMessage(message, "success")
  }

  const showInfoNotification = (error) => {
    showMessage(error, "info")
  }

  const showWarningNotification = (message) => {
    showMessage(message, "warn")
  }

  const showErrorNotification = (error) => {
    let message = error

    if (error.response) {
      if (error.response.data) {
        message = error.response.data["hydra:description"]
      }
    } else if (error.message) {
      message = error.message
    } else if (typeof error === "string") {
      message = error
    }

    showMessage(message, "error")
  }

  const showMessage = (message, severity) => {
    toast.add({
      severity: severity,
      detail: message,
      life: 3500,
    })
  }

  return {
    showSuccessNotification,
    showInfoNotification,
    showWarningNotification,
    showErrorNotification,
  }
}
