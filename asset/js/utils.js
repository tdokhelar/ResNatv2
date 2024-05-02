export function isImageUrl(value) {
  if (!value || typeof value != 'string') return false
  let imagePattern = new RegExp('.*\.(svg|png|jpg|jpeg|gif)')
  let urlPattern = new RegExp('https?:\/\/')
  return imagePattern.test(value.toLowerCase()) || urlPattern.test(value.toLowerCase());
}
