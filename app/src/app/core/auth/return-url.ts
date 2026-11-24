/**
 * Adónde llevar a alguien después de entrar o crear su cuenta: la página que
 * quería abrir (`?volver=`), o «Mis listas».
 *
 * Solo acepta rutas de la propia app: una dirección completa o que empiece con
 * `//` llevaría a otro sitio a quien siguió un enlace preparado.
 */
export function returnUrl(volver: string | null): string {
  if (!volver || !volver.startsWith('/') || volver.startsWith('//') || volver.startsWith('/\\')) {
    return '/listas';
  }
  if (volver.startsWith('/login') || volver.startsWith('/registro')) {
    return '/listas';
  }
  return volver;
}
