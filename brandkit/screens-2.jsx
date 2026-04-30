// BarePitch — Phone screen mocks (set 2)
// Lineup grid focus, Timeline, Public livestream, Match list, Login, Statistics

const ScreenLineupGrid = () => (
  <PhoneFrame label="Lineup grid · 4-3-3">
    <div className="appbar">
      <button className="btn btn-ghost btn-icon"><Icon name="back" /></button>
      <div style={{ flex: 1 }}>
        <h1>Lineup</h1>
        <div style={{ fontSize: 12, color: "var(--ink-3)" }}>FC Wolfsdal · drag to reposition</div>
      </div>
      <button className="btn btn-ghost btn-icon"><Icon name="more" /></button>
    </div>
    <div className="scroll" style={{ padding: 16, display: "flex", flexDirection: "column", gap: 14 }}>
      <PitchMini />
      <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "var(--ink-3)", fontFamily: "var(--font-mono)" }}>
        <span>10 ROWS × 11 COLS</span>
        <span>11/11 PLACED</span>
      </div>
    </div>
  </PhoneFrame>
);

// ── Timeline ─────────────────────────────────
const ScreenTimeline = () => (
  <PhoneFrame label="Match timeline">
    <div className="live-header">
      <div className="team">FC Wolfsdal</div>
      <div className="score t-num"><b>2</b> – 1</div>
      <div className="team away">SV Bergen</div>
    </div>
    <div className="live-meta">
      <span style={{ color: "oklch(0.85 0 0)" }}>Finished</span>
      <span className="timer">FT</span>
      <span style={{ color: "oklch(0.75 0 0)" }}>Sat · Apr 26</span>
    </div>
    <div className="scroll" style={{ overflowY: "auto" }}>
      <div className="timeline">
        {[
          { min: "90+2", icon: "whistle", txt: { who: "Final whistle", what: "Match finished" }, plain: true },
          { min: "78′", icon: "swap", txt: { who: "#6 Mols ↔ #4 de Jong", what: "Substitution · CB" } },
          { min: "66′", iconCls: "goal", icon: "ball", txt: { who: "#9 L. Visser", what: "Goal · zone TR · assist #10" } },
          { min: "52′", iconCls: "yellow", icon: "card", txt: { who: "#4 K. de Jong", what: "Yellow card" } },
          { min: "45′", icon: "whistle", txt: { who: "Half time", what: "1 – 1" }, plain: true },
          { min: "38′", iconCls: "goal", icon: "ball", txt: { who: "#11 J. Boer", what: "Goal · penalty · zone BL" } },
          { min: "22′", iconCls: "red", icon: "card", txt: { who: "SV Bergen #5", what: "Red card · sent off" } },
          { min: "18′", iconCls: "goal", icon: "ball", txt: { who: "SV Bergen", what: "Opponent goal" } },
          { min: "00′", icon: "play", txt: { who: "Kick-off", what: "1st half" }, plain: true },
        ].map((r, i) => (
          <div key={i} className="tl-row">
            <div className="tl-min">{r.min}</div>
            <div className={`tl-icon ${r.iconCls || ""}`}>
              <Icon name={r.icon} className="ico-sm" />
            </div>
            <div className="tl-event">
              <span className="who">{r.txt.who}</span>
              <span className="what">{r.txt.what}</span>
            </div>
            {!r.plain && (
              <button className="tl-edit" aria-label="Edit"><Icon name="edit" className="ico-sm" /></button>
            )}
            {r.plain && <span></span>}
          </div>
        ))}
      </div>
    </div>
  </PhoneFrame>
);

// ── Public livestream ────────────────────────
const ScreenLivestream = () => (
  <PhoneFrame label="Public livestream · viewer">
    <div style={{ padding: 14, background: "var(--bg-2)", display: "flex", justifyContent: "space-between", alignItems: "center", borderBottom: "1px solid var(--line)" }}>
      <span className="bp-logo"><span className="bp-mono">B</span><span className="bp-wordmark">BarePitch</span></span>
      <span className="chip chip-live" style={{ background: "var(--danger)", color: "white", borderColor: "transparent" }}>LIVE</span>
    </div>
    <div className="scroll" style={{ padding: 16, display: "flex", flexDirection: "column", gap: 14 }}>
      <div className="live-pub-card">
        <div className="head">
          <span className="t-mono" style={{ fontSize: 11, letterSpacing: ".06em", textTransform: "uppercase", color: "oklch(0.7 0 0)" }}>2nd half · 68′</span>
          <span className="chip chip-live" style={{ background: "transparent", border: "1px solid oklch(0.4 0 0)", color: "white" }}>Live</span>
        </div>
        <div className="scoreline">
          <div className="team">FC Wolfsdal</div>
          <div className="digits"><b>2</b><span style={{ color: "oklch(0.5 0 0)" }}>:</span>1</div>
          <div className="team away">SV Bergen</div>
        </div>
        <div style={{ padding: "12px 16px", borderTop: "1px solid oklch(0.3 0 0)", display: "flex", justifyContent: "space-between", fontFamily: "var(--font-mono)", fontSize: 11, color: "oklch(0.7 0 0)" }}>
          <span>Sat · 14:30</span>
          <span>Phase 4 · League</span>
          <span>Refresh 60s</span>
        </div>
      </div>

      <div>
        <div className="t-tiny" style={{ marginBottom: 8 }}>Latest events</div>
        <div className="timeline" style={{ border: "1px solid var(--line)", borderRadius: 10, background: "var(--bg)" }}>
          <div className="tl-row">
            <div className="tl-min">66′</div>
            <div className="tl-icon goal"><Icon name="ball" className="ico-sm" /></div>
            <div className="tl-event">
              <span className="who">#9 L. Visser</span>
              <span className="what">Goal · FC Wolfsdal</span>
            </div>
            <span></span>
          </div>
          <div className="tl-row">
            <div className="tl-min">52′</div>
            <div className="tl-icon yellow"><Icon name="card" className="ico-sm" /></div>
            <div className="tl-event">
              <span className="who">#4 K. de Jong</span>
              <span className="what">Yellow card</span>
            </div>
            <span></span>
          </div>
          <div className="tl-row">
            <div className="tl-min">38′</div>
            <div className="tl-icon goal"><Icon name="ball" className="ico-sm" /></div>
            <div className="tl-event">
              <span className="who">#11 J. Boer</span>
              <span className="what">Penalty · FC Wolfsdal</span>
            </div>
            <span></span>
          </div>
        </div>
      </div>

      <div style={{ fontSize: 11, color: "var(--ink-3)", textAlign: "center", marginTop: "auto" }}>
        Public link · expires in 23h 12m · no-store, noindex
      </div>
    </div>
  </PhoneFrame>
);

// ── Match list / dashboard ───────────────────
const ScreenMatchList = () => (
  <PhoneFrame label="Matches · season list">
    <div style={{ padding: "14px 16px 8px", display: "flex", alignItems: "center", justifyContent: "space-between", borderBottom: "1px solid var(--line)" }}>
      <div>
        <div style={{ fontSize: 11, color: "var(--ink-3)", letterSpacing: ".04em", textTransform: "uppercase", fontWeight: 600 }}>FC Wolfsdal · U17</div>
        <h1 style={{ fontSize: 22, margin: "2px 0 0", letterSpacing: "-0.02em", fontWeight: 600 }}>Matches</h1>
      </div>
      <button className="btn btn-primary btn-sm">
        <Icon name="plus" className="ico-sm" /> New
      </button>
    </div>
    <div style={{ padding: "10px 16px", display: "flex", gap: 6, borderBottom: "1px solid var(--line)" }}>
      {["All", "Planned", "Active", "Finished"].map((t, i) => (
        <button key={i} className="btn btn-sm" style={{
          background: i === 0 ? "var(--ink)" : "transparent",
          color: i === 0 ? "var(--ink-inv)" : "var(--ink-2)",
          border: "1px solid " + (i === 0 ? "transparent" : "var(--line)"),
        }}>{t}</button>
      ))}
    </div>
    <div className="scroll" style={{ overflowY: "auto" }}>
      <div className="list-row">
        <span className="dot active"></span>
        <div>
          <div className="title">vs SV Bergen</div>
          <div className="meta">2nd half · 68′ · Home</div>
        </div>
        <div className="right">
          <div className="score-mini" style={{ color: "var(--accent-line)" }}>2 – 1</div>
          <div className="meta">Active</div>
        </div>
      </div>
      <div className="list-row">
        <span className="dot prepared"></span>
        <div>
          <div className="title">vs RKSV Eikhof</div>
          <div className="meta">Sat 11 May · 14:30 · Home</div>
        </div>
        <div className="right">
          <div className="meta">Prepared</div>
          <div className="meta">Phase 4</div>
        </div>
      </div>
      <div className="list-row">
        <span className="dot planned"></span>
        <div>
          <div className="title">vs FC Sandvoort</div>
          <div className="meta">Sat 18 May · 11:00 · Away</div>
        </div>
        <div className="right">
          <div className="meta">Planned</div>
        </div>
      </div>
      <div className="list-row">
        <span className="dot finished"></span>
        <div>
          <div className="title">vs HSV Voorbeek</div>
          <div className="meta">Sat 19 Apr · Away</div>
        </div>
        <div className="right">
          <div className="score-mini">3 – 0</div>
          <div className="meta">Win</div>
        </div>
      </div>
      <div className="list-row">
        <span className="dot finished"></span>
        <div>
          <div className="title">vs SC Tielen</div>
          <div className="meta">Sat 12 Apr · Home</div>
        </div>
        <div className="right">
          <div className="score-mini">1 – 1</div>
          <div className="meta">Draw</div>
        </div>
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

// ── Login ────────────────────────────────────
const ScreenLogin = () => (
  <PhoneFrame label="Login · magic link">
    <div className="login-wrap">
      <div className="brand-stack">
        <span className="bp-mono" style={{ width: 48, height: 48, fontSize: 22, borderRadius: 11 }}>B</span>
      </div>
      <div>
        <h2>Sign in to BarePitch</h2>
        <p className="lede">Enter your email. We'll send a one-time magic link — no password required.</p>
      </div>
      <div className="form">
        <div className="field">
          <label>Email</label>
          <input className="input" placeholder="coach@club.example" defaultValue="coach@wolfsdal.nl" />
        </div>
        <button className="btn btn-primary btn-block btn-lg">
          <Icon name="mail" className="ico" /> Send magic link
        </button>
        <div style={{ fontSize: 11, color: "var(--ink-3)", textAlign: "center", marginTop: 4 }}>
          Link expires in 15 minutes · single use
        </div>
      </div>
      <div style={{ marginTop: "auto", borderTop: "1px solid var(--line)", paddingTop: 14, display: "flex", alignItems: "center", gap: 8, fontSize: 11, color: "var(--ink-3)" }}>
        <Icon name="lock" className="ico-sm" />
        Hashed token storage · rate-limited · HTTPS only
      </div>
    </div>
  </PhoneFrame>
);

// ── Statistics ───────────────────────────────
const ScreenStats = () => (
  <PhoneFrame label="Statistics · season">
    <div style={{ padding: "14px 16px 8px", borderBottom: "1px solid var(--line)" }}>
      <div style={{ fontSize: 11, color: "var(--ink-3)", letterSpacing: ".04em", textTransform: "uppercase", fontWeight: 600 }}>Season 25/26 · League</div>
      <h1 style={{ fontSize: 22, margin: "2px 0 0", letterSpacing: "-0.02em", fontWeight: 600 }}>Statistics</h1>
    </div>
    <div className="scroll" style={{ padding: 16, display: "flex", flexDirection: "column", gap: 16, overflowY: "auto" }}>
      <div style={{ display: "flex", gap: 6 }}>
        {["Team", "Players"].map((t, i) => (
          <button key={i} className="btn btn-sm" style={{
            flex: 1,
            background: i === 0 ? "var(--ink)" : "var(--bg)",
            color: i === 0 ? "var(--ink-inv)" : "var(--ink-2)",
            borderColor: i === 0 ? "transparent" : "var(--line-2)",
            border: "1px solid var(--line-2)",
          }}>{t}</button>
        ))}
      </div>

      <div className="stat-grid">
        <div className="stat-tile">
          <div className="label">Wins</div>
          <div className="val">7</div>
          <div className="delta">↑ 3 vs last phase</div>
        </div>
        <div className="stat-tile">
          <div className="label">Draws</div>
          <div className="val">2</div>
          <div className="delta">—</div>
        </div>
        <div className="stat-tile">
          <div className="label">Losses</div>
          <div className="val">1</div>
          <div className="delta">↓ 2</div>
        </div>
        <div className="stat-tile">
          <div className="label">Goals For</div>
          <div className="val">23</div>
          <div className="delta">2.30 / match</div>
        </div>
      </div>

      <div className="panel-flat" style={{ padding: 14 }}>
        <div className="t-tiny" style={{ marginBottom: 10 }}>Top scorer</div>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <span className="player-num" style={{ width: 36, height: 36, fontSize: 14 }}>9</span>
          <div style={{ flex: 1 }}>
            <div className="player-name" style={{ fontSize: 15 }}>L. Visser</div>
            <div className="player-meta">FW · 8 matches · 2 assists</div>
          </div>
          <div style={{ fontFamily: "var(--font-mono)", fontSize: 24, fontWeight: 600, letterSpacing: "-0.02em" }}>9</div>
        </div>
      </div>

      <div>
        <div className="t-tiny" style={{ marginBottom: 8 }}>Recent form</div>
        <div style={{ display: "flex", gap: 6 }}>
          {[
            { r: "W", color: "var(--accent)" },
            { r: "W", color: "var(--accent)" },
            { r: "D", color: "var(--bg-3)" },
            { r: "W", color: "var(--accent)" },
            { r: "L", color: "var(--danger)" },
          ].map((g, i) => (
            <div key={i} style={{
              flex: 1,
              height: 38,
              borderRadius: 8,
              background: g.color,
              color: g.color === "var(--accent)" ? "var(--accent-ink)" : (g.color === "var(--danger)" ? "var(--danger-ink)" : "var(--ink-2)"),
              display: "flex", alignItems: "center", justifyContent: "center",
              fontFamily: "var(--font-mono)", fontWeight: 700, fontSize: 14,
            }}>{g.r}</div>
          ))}
        </div>
      </div>
    </div>
  </PhoneFrame>
);

Object.assign(window, { ScreenLineupGrid, ScreenTimeline, ScreenLivestream, ScreenMatchList, ScreenLogin, ScreenStats });
