import {
  Animation,
  AnimationBuilder,
  createAnimation,
  iosTransitionAnimation,
  mdTransitionAnimation,
} from '@ionic/angular';

/**
 * Animaciones al cambiar de pantalla, para elegir una desde el menú de administrador.
 *
 * Ionic las llama con la página que entra y la que sale. Al ir hacia adelante (menú o abrir algo)
 * la que entra queda encima; al volver queda debajo, y la que sale encima se destruye después.
 */
export interface PageTransition {
  id: string;
  name: string;
  description: string;
  builder: AnimationBuilder;
}

/** Lo que Ionic le pasa a la animación: las dos páginas, la dirección y el modo (ios o md). */
type TransitionOptions = Parameters<typeof mdTransitionAnimation>[1];

interface Timing {
  duration: number;
  easing: string;
}

/** Arma una transición: `animate` le pone movimiento a la página que entra y a la que sale. */
function transition(
  timing: Timing | ((back: boolean) => Timing),
  animate: (entering: Animation, leaving: Animation | null, back: boolean) => void,
): AnimationBuilder {
  return (_baseEl: HTMLElement, opts: TransitionOptions) => {
    const back = opts.direction === 'back';
    const { duration, easing } = typeof timing === 'function' ? timing(back) : timing;
    const root = createAnimation().duration(duration).easing(easing);

    const entering = createAnimation()
      .addElement(pageOf(opts.enteringEl))
      .fill('both')
      .beforeRemoveClass('ion-page-invisible');
    root.addAnimation(entering);

    let leaving: Animation | null = null;
    if (opts.leavingEl) {
      const leavingPage = pageOf(opts.leavingEl);
      leaving = createAnimation().addElement(leavingPage).fill('both');
      if (back) {
        // Al volver, la que sale está encima: escondida al terminar, no reaparece entera un instante
        // entre que se borra la animación y Ionic la quita.
        leaving.onFinish((step) => {
          if (step === 1) {
            leavingPage.style.setProperty('display', 'none');
          }
        });
      }
      root.addAnimation(leaving);
    }

    animate(entering, leaving, back);
    return root;
  };
}

function pageOf(element: HTMLElement): HTMLElement {
  if (element.classList.contains('ion-page')) {
    return element;
  }
  return element.querySelector<HTMLElement>(':scope > .ion-page') ?? element;
}

const STANDARD = 'cubic-bezier(0.2, 0, 0, 1)';

/** La que sale se apaga en el primer tercio y la que entra aparece después: nunca se mezclan. */
const OUT_FIRST = 0.35;

export const PAGE_TRANSITIONS: PageTransition[] = [
  {
    id: 'ionic',
    name: 'La de ahora',
    description:
      'La de Ionic: en Android la pantalla nueva sube un poco mientras aparece; en iPhone entra de lado.',
    // Ionic elige la suya según el modo con que corre la app; esta hace lo mismo.
    builder: (baseEl: HTMLElement, opts: TransitionOptions) =>
      opts.mode === 'ios'
        ? iosTransitionAnimation(baseEl, opts)
        : mdTransitionAnimation(baseEl, opts),
  },
  {
    id: 'fundido',
    name: 'Fundido',
    description: 'La nueva aparece encima de la anterior, sin moverse.',
    builder: transition({ duration: 220, easing: 'ease-out' }, (entering, leaving, back) => {
      if (back) {
        leaving?.fromTo('opacity', '1', '0');
      } else {
        entering.fromTo('opacity', '0', '1');
      }
    }),
  },
  {
    id: 'pasar',
    name: 'Pasar a través',
    description: 'La anterior se apaga y la nueva aparece creciendo apenas. Sin mezclarse.',
    builder: transition({ duration: 300, easing: STANDARD }, (entering, leaving) => {
      leaving?.keyframes([
        { offset: 0, opacity: '1' },
        { offset: OUT_FIRST, opacity: '0' },
        { offset: 1, opacity: '0' },
      ]);
      entering.keyframes([
        { offset: 0, opacity: '0', transform: 'scale(0.92)' },
        { offset: OUT_FIRST, opacity: '0', transform: 'scale(0.92)' },
        { offset: 1, opacity: '1', transform: 'scale(1)' },
      ]);
    }),
  },
  {
    id: 'lateral',
    name: 'Deslizar de lado',
    description: 'La nueva entra desde la derecha y empuja la anterior, como en un iPhone.',
    builder: transition(
      { duration: 380, easing: 'cubic-bezier(0.32, 0.72, 0, 1)' },
      (entering, leaving, back) => {
        if (back) {
          leaving?.fromTo('transform', 'translateX(0)', 'translateX(100%)');
          entering
            .fromTo('transform', 'translateX(-25%)', 'translateX(0)')
            .fromTo('opacity', '0.7', '1');
        } else {
          entering.fromTo('transform', 'translateX(100%)', 'translateX(0)');
          leaving
            ?.fromTo('transform', 'translateX(0)', 'translateX(-25%)')
            .fromTo('opacity', '1', '0.7');
        }
      },
    ),
  },
  {
    id: 'eje-x',
    name: 'Deslizar corto',
    description:
      'Las dos se corren unos pocos píxeles de lado mientras una se apaga y la otra aparece.',
    builder: transition({ duration: 300, easing: STANDARD }, (entering, leaving, back) => {
      const shift = back ? -30 : 30;
      leaving?.keyframes([
        { offset: 0, opacity: '1', transform: 'translateX(0)' },
        { offset: OUT_FIRST, opacity: '0', transform: `translateX(${-shift * OUT_FIRST}px)` },
        { offset: 1, opacity: '0', transform: `translateX(${-shift}px)` },
      ]);
      entering.keyframes([
        { offset: 0, opacity: '0', transform: `translateX(${shift}px)` },
        { offset: OUT_FIRST, opacity: '0', transform: `translateX(${shift * (1 - OUT_FIRST)}px)` },
        { offset: 1, opacity: '1', transform: 'translateX(0)' },
      ]);
    }),
  },
  {
    id: 'profundidad',
    name: 'Profundidad',
    description:
      'Como avanzar hacia adentro: la anterior se agranda y se va, la nueva llega desde el fondo.',
    builder: transition({ duration: 320, easing: STANDARD }, (entering, leaving, back) => {
      const [leaveTo, enterFrom] = back
        ? ['scale(0.85)', 'scale(1.1)']
        : ['scale(1.1)', 'scale(0.85)'];
      leaving?.keyframes([
        { offset: 0, opacity: '1', transform: 'scale(1)' },
        { offset: OUT_FIRST, opacity: '0' },
        { offset: 1, opacity: '0', transform: leaveTo },
      ]);
      entering.keyframes([
        { offset: 0, opacity: '0', transform: enterFrom },
        { offset: OUT_FIRST, opacity: '0' },
        { offset: 1, opacity: '1', transform: 'scale(1)' },
      ]);
    }),
  },
  {
    id: 'desenfoque',
    name: 'Desenfoque',
    description: 'La anterior se desenfoca hasta desaparecer y la nueva se enfoca.',
    builder: transition({ duration: 340, easing: STANDARD }, (entering, leaving) => {
      leaving?.keyframes([
        { offset: 0, opacity: '1', filter: 'blur(0)' },
        { offset: 0.5, opacity: '0', filter: 'blur(10px)' },
        { offset: 1, opacity: '0', filter: 'blur(10px)' },
      ]);
      entering.keyframes([
        { offset: 0, opacity: '0', filter: 'blur(10px)' },
        { offset: 0.3, opacity: '0', filter: 'blur(10px)' },
        { offset: 1, opacity: '1', filter: 'blur(0)' },
      ]);
    }),
  },
  {
    id: 'barrido',
    name: 'Barrido',
    description: 'La nueva se descubre de izquierda a derecha, como una cortina que se corre.',
    builder: transition(
      { duration: 420, easing: 'cubic-bezier(0.65, 0, 0.35, 1)' },
      (entering, leaving, back) => {
        // clipPath y no clip-path: las animaciones del navegador piden el nombre en camelCase.
        if (back) {
          leaving?.fromTo('clipPath', 'inset(0 0 0 0)', 'inset(0 100% 0 0)');
        } else {
          entering.fromTo('clipPath', 'inset(0 100% 0 0)', 'inset(0 0 0 0)');
        }
      },
    ),
  },
  {
    id: 'resorte',
    name: 'Resorte',
    description: 'La nueva entra de lado y se pasa un poquito antes de quedar en su lugar.',
    builder: transition(
      (back) =>
        back
          ? { duration: 240, easing: 'cubic-bezier(0.4, 0, 1, 1)' }
          : { duration: 480, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' },
      (entering, leaving, back) => {
        if (back) {
          leaving
            ?.fromTo('transform', 'translateX(0)', 'translateX(60px)')
            .fromTo('opacity', '1', '0');
        } else {
          entering
            .fromTo('transform', 'translateX(60px)', 'translateX(0)')
            .fromTo('opacity', '0', '1');
        }
      },
    ),
  },
  {
    id: 'ninguna',
    name: 'Sin animación',
    description: 'La pantalla cambia de golpe.',
    builder: transition({ duration: 0, easing: 'linear' }, () => undefined),
  },
];
