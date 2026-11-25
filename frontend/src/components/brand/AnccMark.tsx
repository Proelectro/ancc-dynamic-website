/**
 * The ANCC logo mark, < /\ || (( >, drawn as SVG so it stays sharp at any size.
 * Orange strokes use the brand orange; the strokes that are white in the original logo use
 * currentColor, so they show as white on slate and as slate on light backgrounds.
 * Originals: public/brand/ancc-logo.jpg and ancc-logo-transparent.png.
 */
export function AnccMark({ className, title = "ANCC" }: { className?: string; title?: string }) {
  return (
    <svg
      viewBox="40 500 1200 200"
      className={className}
      role="img"
      aria-label={title}
      fill="none"
      strokeWidth={14}
      strokeLinecap="butt"
    >
      <g stroke="var(--brand)">
        <polyline points="157,543 62,601 157,658" strokeLinejoin="miter" />
        <line x1="307" y1="681" x2="382" y2="518" />
        <line x1="600" y1="511" x2="600" y2="685" />
        <line x1="709" y1="511" x2="709" y2="685" />
        <path d="M965 514 A94 94 0 0 0 965 682" />
        <polyline points="1125,543 1220,601 1125,658" strokeLinejoin="miter" />
      </g>
      <g stroke="currentColor">
        <line x1="426" y1="518" x2="501" y2="681" />
        <path d="M860 514 A94 94 0 0 0 860 682" />
      </g>
    </svg>
  );
}
