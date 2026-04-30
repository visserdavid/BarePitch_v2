// BarePitch — Phone screen mocks (set 1)
// Live match, Match prep, Goal modal, Substitution

const PhoneFrame = ({ children, label, time = "14:32" }) => (
  <div>
    <div className="phone">
      <div className="phone-statusbar">
        <span>{time}</span>
        <span>•••• 5G</span>
      </div>
      <div className="phone-screen phone-body">
        {children}
      </div>
    </div>
    {label && <span className="phone-label">{label}</span>}
  </div>
);

// ── Live match screen ─────────────────────────
const ScreenLiveMatch = () => (
  <PhoneFrame label="Live match · active phase">
    <div className="live-header">
      <div className="team">
        <div style={{ fontSize: 11, color: "oklch(0.7 0 0)", letterSpacing: ".06em", textTransform: "uppercase", marginBottom: 4 }}>Home</div>
        FC Wolfsdal
      </div>
      <div className="score t-num"><b>2</b> – 1</div>
      <div className="team away">
        <div style={{ fontSize: 11, color: "oklch(0.7 0 0)", letterSpacing: ".06em", textTransform: "uppercase", marginBottom: 4 }}>Away</div>
        SV Bergen
      </div>
    </div>
    <div className="live-meta">
      <span className="chip chip-live" style={{ background: "transparent", border: "1px solid oklch(0.45 0 0)", color: "oklch(0.92 0 0)" }}>2nd Half</span>
      <span className="timer">68′</span>
      <span style={{ color: "oklch(0.75 0 0)" }}>11 vs 11</span>
    </div>

    <div className="scroll" style={{ padding: 16, display: "flex", flexDirection: "column", gap: 16 }}>
      <div>
        <div className="t-tiny" style={{ marginBottom: 10 }}>Quick events</div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 8 }}>
          {[
            { icon: "ball", label: "Goal", primary: true },
            { icon: "swap", label: "Sub" },
            { icon: "card", label: "Yellow", warn: true },
            { icon: "card", label: "Red", danger: true },
          ].map((b, i) => (
            <button key={i} className="btn btn-secondary" style={{
              flexDirection: "column",
              height: 72,
              gap: 4,
              fontSize: 11,
              padding: 0,
              ...(b.primary ? { background: "var(--accent)", color: "var(--accent-ink)", borderColor: "transparent" } : {}),
              ...(b.warn ? { background: "color-mix(in oklch, var(--warn) 28%, var(--bg))", color: "var(--warn-ink)", borderColor: "transparent" } : {}),
              ...(b.danger ? { background: "var(--danger)", color: "var(--danger-ink)", borderColor: "transparent" } : {}),
            }}>
              <Icon name={b.icon} className="ico-lg" />
              <span>{b.label}</span>
            </button>
          ))}
        </div>
      </div>

      <div>
        <div className="t-tiny" style={{ marginBottom: 10 }}>Recent</div>
        <div className="timeline" style={{ borderRadius: 10, border: "1px solid var(--line)" }}>
          <div className="tl-row">
            <div className="tl-min">66′</div>
            <div className="tl-icon goal"><Icon name="ball" className="ico-sm" /></div>
            <div className="tl-event">
              <span className="who">#9 L. Visser</span>
              <span className="what">Goal · assist #11 J. Boer</span>
            </div>
            <button className="tl-edit" aria-label="Edit"><Icon name="edit" className="ico-sm" /></button>
          </div>
          <div className="tl-row">
            <div className="tl-min">52′</div>
            <div className="tl-icon yellow"><Icon name="card" className="ico-sm" /></div>
            <div className="tl-event">
              <span className="who">#4 K. de Jong</span>
              <span className="what">Yellow card</span>
            </div>
            <button className="tl-edit" aria-label="Edit"><Icon name="edit" className="ico-sm" /></button>
          </div>
        </div>
      </div>

      <div style={{ marginTop: "auto" }}>
        <button className="btn btn-ink btn-block btn-lg">
          <Icon name="whistle" className="ico" /> End 2nd half
        </button>
      </div>
    </div>

    <div className="bottom-nav">
      {[
        { i: "home", l: "Home" },
        { i: "team", l: "Team" },
        { i: "matches", l: "Matches", active: true },
        { i: "stats", l: "Stats" },
        { i: "settings", l: "More" },
      ].map((n, i) => (
        <button key={i} className={n.active ? "active" : ""}>
          <Icon name={n.i} className="ico" />
          <span>{n.l}</span>
        </button>
      ))}
    </div>
  </PhoneFrame>
);

// ── Match prep ──────────────────────────────
const ScreenMatchPrep = () => (
  <PhoneFrame label="Match preparation">
    <div className="appbar">
      <button className="btn btn-ghost btn-icon"><Icon name="back" /></button>
      <div style={{ flex: 1 }}>
        <h1>Prepare match</h1>
        <div style={{ fontSize: 12, color: "var(--ink-3)" }}>vs SV Bergen · Sat 14:30</div>
      </div>
      <span className="chip">Step 2/3</span>
    </div>

    <div className="scroll" style={{ padding: 16, display: "flex", flexDirection: "column", gap: 18 }}>
      <div>
        <div className="t-tiny" style={{ marginBottom: 8 }}>Formation</div>
        <div style={{ display: "flex", gap: 6, overflowX: "auto", scrollbarWidth: "none" }}>
          {["4-3-3", "4-4-2", "3-5-2", "4-2-3-1", "5-3-2"].map((f, i) => (
            <button key={i} className="btn btn-sm" style={{
              flexShrink: 0,
              background: i === 0 ? "var(--ink)" : "var(--bg)",
              color: i === 0 ? "var(--ink-inv)" : "var(--ink)",
              borderColor: i === 0 ? "transparent" : "var(--line-2)",
              border: "1px solid var(--line-2)",
              fontFamily: "var(--font-mono)",
            }}>{f}</button>
          ))}
        </div>
      </div>

      <PitchMini />

      <div>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: 6 }}>
          <div className="t-tiny">Bench · 5</div>
          <button className="btn btn-ghost btn-sm"><Icon name="plus" className="ico-sm" /> Guest</button>
        </div>
        <div className="bench">
          {[
            { n: 1, name: "Berg" },
            { n: 6, name: "Mols" },
            { n: 14, name: "Smit" },
            { n: 17, name: "Aydın" },
            { n: 19, name: "Boer (G)" },
          ].map((p, i) => (
            <div key={i} className="bench-player">
              <span className="num">{p.n}</span>
              <span className="name">{p.name}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="checklist">
        <div className="row done">
          <div className="check"><Icon name="check" className="ico-sm" /></div>
          <span className="label">11 starters placed</span>
          <span className="meta">11/11</span>
        </div>
        <div className="row done">
          <div className="check"><Icon name="check" className="ico-sm" /></div>
          <span className="label">All starters present</span>
          <span className="meta">OK</span>
        </div>
        <div className="row">
          <div className="check"></div>
          <span className="label">No starter injured</span>
          <span className="meta">1 issue</span>
        </div>
      </div>

      <button className="btn btn-primary btn-block btn-lg" style={{ marginTop: "auto" }}>
        Mark prepared <Icon name="right" className="ico" />
      </button>
    </div>
  </PhoneFrame>
);

const PitchMini = () => {
  // 4-3-3 positions on a 10×14 grid feel
  const players = [
    { x: 50, y: 92, n: 1, name: "Berg", gk: true },
    { x: 16, y: 75, n: 2, name: "Aziz" },
    { x: 38, y: 78, n: 4, name: "de Jong" },
    { x: 62, y: 78, n: 5, name: "Visser" },
    { x: 84, y: 75, n: 3, name: "Olde" },
    { x: 28, y: 55, n: 8, name: "Bakker" },
    { x: 50, y: 58, n: 6, name: "Hoek" },
    { x: 72, y: 55, n: 10, name: "El Amrani" },
    { x: 22, y: 30, n: 7, name: "Smit" },
    { x: 50, y: 22, n: 9, name: "Visser" },
    { x: 78, y: 30, n: 11, name: "Boer" },
  ];
  return (
    <div className="pitch">
      <svg className="pitch-lines" viewBox="0 0 100 130" preserveAspectRatio="none">
        <g stroke="oklch(1 0 0 / 0.45)" strokeWidth="0.4" fill="none">
          <rect x="2" y="2" width="96" height="126" />
          <line x1="2" y1="65" x2="98" y2="65" />
          <circle cx="50" cy="65" r="10" />
          <rect x="22" y="2" width="56" height="14" />
          <rect x="34" y="2" width="32" height="6" />
          <rect x="22" y="114" width="56" height="14" />
          <rect x="34" y="122" width="32" height="6" />
        </g>
      </svg>
      {players.map((p, i) => (
        <div key={i} className={`player-pin${p.gk ? " gk" : ""}`} style={{ left: `${p.x}%`, top: `${p.y}%` }}>
          {p.n}
          <span className="name">{p.name}</span>
        </div>
      ))}
    </div>
  );
};

// ── Goal modal ────────────────────────────
const ScreenGoalModal = () => {
  const cells = [
    "TL","TC","TR",
    "ML","MC","MR",
    "BL","BC","BR",
  ];
  const selected = "TR";
  return (
    <PhoneFrame label="Goal registration · zone select">
      <div className="live-header">
        <div className="team">FC Wolfsdal</div>
        <div className="score t-num">2 – 1</div>
        <div className="team away">SV Bergen</div>
      </div>
      <div className="live-meta">
        <span style={{ color: "oklch(0.85 0 0)" }}>2nd Half</span>
        <span className="timer">66′</span>
        <span style={{ color: "oklch(0.75 0 0)" }}>11 vs 11</span>
      </div>
      <div style={{ flex: 1, position: "relative", background: "color-mix(in oklch, var(--ink) 6%, transparent)" }}>
        <div className="modal-backdrop" style={{ position: "absolute" }}>
          <div className="modal-sheet">
            <div className="modal-handle"></div>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <h2 className="modal-title">Goal · #9 L. Visser</h2>
              <button className="btn btn-ghost btn-icon btn-sm"><Icon name="x" className="ico-sm" /></button>
            </div>

            <div>
              <div className="t-tiny" style={{ marginBottom: 8 }}>Goal zone <span style={{ color: "var(--ink-3)", textTransform: "none", letterSpacing: 0, fontWeight: 500 }}>· optional</span></div>
              <div className="goalzone">
                {cells.map(c => (
                  <div key={c} className={`cell${c === selected ? " selected" : ""}`}>{c}</div>
                ))}
              </div>
            </div>

            <div>
              <div className="t-tiny" style={{ marginBottom: 8 }}>Assist <span style={{ color: "var(--ink-3)", textTransform: "none", letterSpacing: 0, fontWeight: 500 }}>· optional</span></div>
              <div className="player-list">
                <button className="player-cell">
                  <span className="player-num">11</span>
                  <span>
                    <div className="player-name">J. Boer</div>
                    <div className="player-meta">RW · on field</div>
                  </span>
                </button>
                <button className="player-cell selected">
                  <span className="player-num">10</span>
                  <span>
                    <div className="player-name">El Amrani</div>
                    <div className="player-meta">CM · on field</div>
                  </span>
                </button>
                <button className="player-cell">
                  <span className="player-num">7</span>
                  <span>
                    <div className="player-name">Smit</div>
                    <div className="player-meta">LW · on field</div>
                  </span>
                </button>
                <button className="player-cell">
                  <span className="player-num">8</span>
                  <span>
                    <div className="player-name">Bakker</div>
                    <div className="player-meta">CM · on field</div>
                  </span>
                </button>
              </div>
            </div>

            <div style={{ display: "flex", gap: 8 }}>
              <button className="btn btn-secondary" style={{ flex: 1 }}>Cancel</button>
              <button className="btn btn-primary" style={{ flex: 2 }}>
                <Icon name="check" className="ico-sm" /> Register goal
              </button>
            </div>
          </div>
        </div>
      </div>
    </PhoneFrame>
  );
};

// ── Substitution flow ─────────────────────
const ScreenSubstitution = () => (
  <PhoneFrame label="Substitution · pick incoming">
    <div className="appbar">
      <button className="btn btn-ghost btn-icon"><Icon name="back" /></button>
      <div style={{ flex: 1 }}>
        <h1>Substitution</h1>
        <div style={{ fontSize: 12, color: "var(--ink-3)" }}>68′ · 2nd Half</div>
      </div>
    </div>

    <div className="scroll" style={{ padding: 16, display: "flex", flexDirection: "column", gap: 16 }}>
      <div className="panel-flat" style={{ padding: 14 }}>
        <div className="t-tiny" style={{ marginBottom: 8 }}>Outgoing</div>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <span className="player-num" style={{ width: 36, height: 36, fontSize: 14 }}>4</span>
          <div style={{ flex: 1 }}>
            <div className="player-name" style={{ fontSize: 15 }}>K. de Jong</div>
            <div className="player-meta">CB · 68′ played · <span className="cardflag yellow"></span> yellow</div>
          </div>
          <Icon name="swap" className="ico" />
        </div>
      </div>

      <div>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: 8 }}>
          <div className="t-tiny">Incoming · bench</div>
          <span className="t-mono" style={{ fontSize: 11, color: "var(--ink-3)" }}>5 available</span>
        </div>
        <div className="player-list">
          {[
            { n: 6, name: "Mols", pos: "CB", sel: true },
            { n: 14, name: "Smit", pos: "RB" },
            { n: 17, name: "Aydın", pos: "CM" },
            { n: 19, name: "Boer", pos: "FW · guest" },
          ].map((p, i) => (
            <button key={i} className={`player-cell${p.sel ? " selected" : ""}`}>
              <span className="player-num">{p.n}</span>
              <span>
                <div className="player-name">{p.name}</div>
                <div className="player-meta">{p.pos}</div>
              </span>
            </button>
          ))}
        </div>
      </div>

      <div style={{ marginTop: "auto", display: "flex", flexDirection: "column", gap: 10 }}>
        <div className="t-tiny" style={{ textAlign: "center" }}>Swipe to confirm</div>
        <div className="swipe">
          <div className="swipe-track">SWIPE TO SUB IN #6 MOLS</div>
          <div className="swipe-thumb" style={{ left: "32%" }}>
            <Icon name="right" className="ico-sm" />
          </div>
        </div>
      </div>
    </div>
  </PhoneFrame>
);

Object.assign(window, { PhoneFrame, ScreenLiveMatch, ScreenMatchPrep, ScreenGoalModal, ScreenSubstitution, PitchMini });
