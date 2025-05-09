// common validators across base components

import { chamiloIconToClass } from "./ChamiloIcons"

export const iconValidator = (value) => {
  if (typeof value !== "string") {
    return false
  }

  return Object.keys(chamiloIconToClass).includes(value)
}

export const sizeValidator = (value) => {
  if (typeof value !== "string") {
    return false
  }

  return ["normal", "small"].includes(value)
}

export const buttonTypeValidator = (value) => {
  if (typeof value !== "string") {
    return false
  }

  return ["primary", "primary-alternative", "secondary", "black", "success", "info", "warning", "danger"].includes(
    value,
  )
}
