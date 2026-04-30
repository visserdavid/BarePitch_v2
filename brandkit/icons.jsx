// BarePitch icon set — outline, 24x24, stroke 1.7
// Currentcolor for theming.

const Icon = ({ name, className = "ico", strokeWidth }) => {
  const sw = strokeWidth || 1.7;
  const common = {
    width: 24, height: 24, viewBox: "0 0 24 24",
    fill: "none", stroke: "currentColor",
    strokeWidth: sw, strokeLinecap: "round", strokeLinejoin: "round",
    className,
  };
  switch (name) {
    case "ball": // soccer ball — pentagon on circle
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7.2l3.7 2.7-1.4 4.35h-4.6L8.3 9.9z" />
          <path d="M12 7.2V3.5M15.7 9.9l3.5-1.2M14.3 14.25l2.4 3M9.7 14.25l-2.4 3M8.3 9.9L4.8 8.7" />
        </svg>
      );
    case "swap": // substitution
      return (
        <svg {...common}>
          <path d="M4 8h13l-3-3M20 16H7l3 3" />
        </svg>
      );
    case "card": // card (use color via classname)
      return (
        <svg {...common}>
          <rect x="7" y="3" width="10" height="18" rx="1.5" />
        </svg>
      );
    case "injury": // medical cross in rounded square
      return (
        <svg {...common}>
          <rect x="3.5" y="3.5" width="17" height="17" rx="3" />
          <path d="M12 8v8M8 12h8" />
        </svg>
      );
    case "note":
      return (
        <svg {...common}>
          <path d="M5 4h11l3 3v13H5z" />
          <path d="M8 11h8M8 15h5" />
        </svg>
      );
    case "trash":
      return (
        <svg {...common}>
          <path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" />
        </svg>
      );
    case "edit":
      return (
        <svg {...common}>
          <path d="M4 20h4l10-10-4-4L4 16zM14 6l4 4" />
        </svg>
      );
    case "clock":
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v5l3 2" />
        </svg>
      );
    case "play":
      return (
        <svg {...common}>
          <path d="M7 4l13 8-13 8z" fill="currentColor" stroke="none" />
        </svg>
      );
    case "pause":
      return (
        <svg {...common}>
          <rect x="6" y="4" width="4" height="16" rx="1" fill="currentColor" stroke="none" />
          <rect x="14" y="4" width="4" height="16" rx="1" fill="currentColor" stroke="none" />
        </svg>
      );
    case "cone": // training cone
      return (
        <svg {...common}>
          <path d="M8.9 10h6.2M6.9 15h10.2M3 20h18M5 20l5.3-13.6c.53-1.36.79-2.04 1.17-2.24a1 1 0 0 1 1.05 0c.38.2.65.88 1.18 2.24L19 20" />
        </svg>
      );
    case "whistle": // referee whistle with sound waves
      return (
        <svg {...common}>
          <path d="M22.5 12.9l-7.65 2.87a6.7 6.7 0 1 1-6.7-6.69H11V12l3.83-1V9.11h7.65z" />
          <path d="M13 1v2.4M7.5 3.2l1.7 1.7M18.5 3.2l-1.7 1.7" />
        </svg>
      );
    case "team":
      return (
        <svg {...common}>
          <circle cx="9" cy="9" r="3" />
          <path d="M3 19c1-3 3-5 6-5s5 2 6 5" />
          <circle cx="17" cy="7" r="2" />
          <path d="M15 14c2-1 4-1 6 1" />
        </svg>
      );
    case "player":
      return (
        <svg {...common}>
          <circle cx="12" cy="8" r="3.4" />
          <path d="M5 20c1.4-3.4 4.2-5 7-5s5.6 1.6 7 5" />
        </svg>
      );
    case "matches":
      return (
        <svg {...common}>
          <rect x="3" y="5" width="18" height="14" rx="2" />
          <path d="M3 10h18M12 5v14" />
        </svg>
      );
    case "stats":
      return (
        <svg {...common}>
          <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
        </svg>
      );
    case "settings":
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="2.6" />
          <path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4" />
        </svg>
      );
    case "home":
      return (
        <svg {...common}>
          <path d="M4 11l8-7 8 7v9H4z" />
          <path d="M10 20v-6h4v6" />
        </svg>
      );
    case "guest":
      return (
        <svg {...common}>
          <circle cx="10" cy="9" r="3.4" />
          <path d="M3 20c1.4-3.4 4.2-5 7-5s5.6 1.6 7 5" />
          <path d="M20 6v6M17 9h6" />
        </svg>
      );
    case "back":
      return (
        <svg {...common}>
          <path d="M14 6l-6 6 6 6M8 12h12" />
        </svg>
      );
    case "more":
      return (
        <svg {...common}>
          <circle cx="6" cy="12" r="1.4" fill="currentColor" stroke="none"/>
          <circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/>
          <circle cx="18" cy="12" r="1.4" fill="currentColor" stroke="none"/>
        </svg>
      );
    case "plus":
      return (
        <svg {...common}>
          <path d="M12 5v14M5 12h14" />
        </svg>
      );
    case "check":
      return (
        <svg {...common}>
          <path d="M5 12l4 4 10-10" />
        </svg>
      );
    case "x":
      return (
        <svg {...common}>
          <path d="M6 6l12 12M18 6L6 18" />
        </svg>
      );
    case "lock":
      return (
        <svg {...common}>
          <rect x="5" y="11" width="14" height="9" rx="2" />
          <path d="M8 11V7a4 4 0 0 1 8 0v4" />
        </svg>
      );
    case "mail":
      return (
        <svg {...common}>
          <rect x="3" y="6" width="18" height="13" rx="2" />
          <path d="M3 8l9 6 9-6" />
        </svg>
      );
    case "broadcast":
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="2" />
          <path d="M8 8a5 5 0 0 0 0 8M16 8a5 5 0 0 1 0 8" />
          <path d="M5 5a10 10 0 0 0 0 14M19 5a10 10 0 0 1 0 14" />
        </svg>
      );
    case "field": // portrait pitch
      return (
        <svg {...common}>
          <rect x="5" y="3" width="14" height="18" rx="1" />
          <path d="M5 12h14" />
          <rect x="9" y="3" width="6" height="4" />
          <rect x="9" y="17" width="6" height="4" />
          <circle cx="12" cy="12" r="1.6" />
        </svg>
      );
    case "right":
      return (
        <svg {...common}>
          <path d="M9 6l6 6-6 6" />
        </svg>
      );
    case "moon":
      return (
        <svg {...common}>
          <path d="M20 14a8 8 0 1 1-9-11 6.5 6.5 0 0 0 9 11z" />
        </svg>
      );
    case "sun":
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="3.6" />
          <path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4" />
        </svg>
      );
    default:
      return null;
  }
};

window.Icon = Icon;
