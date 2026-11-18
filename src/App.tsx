import { useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import { ArrowDownRight, ArrowRight, ArrowUpRight, Menu, X } from "lucide-react";
import { motion, useReducedMotion } from "framer-motion";
import { pillars, universes } from "./data";
import Admin from "./Admin";

const filters = ["Todos", "Estratégia", "Imagem", "Editorial", "Infantil", "Personalização"] as const;

function UniverseArtwork({ id, compact = false }: { id: string; compact?: boolean }) {
  return <div className={`universe-artwork artwork-${id}${compact ? " is-compact" : ""}`} aria-hidden="true">
    <span className="art-shape shape-one" /><span className="art-shape shape-two" /><span className="art-shape shape-three" />
    <span className="art-monogram">{id === "agencia" ? "F/" : id === "fotografia" ? "◉" : id === "editora" ? "E" : id === "tio-moises" ? "✳" : "□"}</span>
    <span className="art-caption">{id === "agencia" ? "IDEAS / STRATEGY" : id === "fotografia" ? "LIGHT / FRAME" : id === "editora" ? "PAPER / STORIES" : id === "tio-moises" ? "PLAY / LEARN" : "OBJECT / FORM"}</span>
  </div>;
}

function PublicPortal() {
  const [contact, setContact] = useState({ name: "", email: "", subject: "", message: "", website: "" });
  const [publicUniverses, setPublicUniverses] = useState(universes);
  const [portalCopy, setPortalCopy] = useState({ headline: "Ideias que ganham forma.", subheadline: "Marcas que deixam marcas.", intro: "Somos um grupo que conecta criatividade, comunicação, conhecimento e inovação para transformar ideias em experiências com significado.", groupHeadline: "Um grupo. Muitas formas de transformar.", groupLead: "A FIXXAR GROUP reúne diferentes competências em um ecossistema que transforma ideias em projetos, marcas e experiências.", groupBody: "Atuamos em universos distintos, mas conectados por uma mesma visão: fazer bem-feito, com propósito, consistência e atenção aos detalhes.", processHeadline: "O movimento por trás da ideia.", processIntro: "Uma ideia merece mais do que existir. Ela merece encontrar seu caminho, ganhar consistência e chegar às pessoas.", processSteps: [{title:"Imaginar",text:"Enxergar possibilidades e descobrir o que ainda pode existir."},{title:"Construir",text:"Dar estrutura, linguagem e intenção para cada projeto."},{title:"Conectar",text:"Aproximar ideias, pessoas e experiências que fazem sentido."},{title:"Transformar",text:"Levar o que foi imaginado para o mundo real."}] });
  useEffect(() => {
    let active = true;
    fetch("/api/public/content", { headers: { Accept: "application/json" }, credentials: "same-origin" })
      .then(response => { if (!response.ok) throw new Error("Public content unavailable"); return response.json(); })
      .then((data: { page?: { content?: Partial<typeof portalCopy> } | null; universes?: Array<{ slug: string; name: string; eyebrow: string; description: string; detail: string; category: string; website_url?: string | null; status: string }> }) => {
        if (!active) return;
        if (data.page?.content) setPortalCopy(current => ({ ...current, ...data.page?.content }));
        if (data.universes?.length) setPublicUniverses(data.universes.map(item => ({ id: item.slug, name: item.name, eyebrow: item.eyebrow, description: item.description, detail: item.detail, category: item.category as (typeof universes)[number]["category"], url: item.website_url || undefined, status: item.status === "active" ? "active" : "in-development" })));
      }).catch(() => { /* Static content remains available when the API is not configured. */ });
    return () => { active = false; };
  }, []);
  const [contactState, setContactState] = useState<"idle" | "sending" | "sent" | "error">("idle");
  const [contactError, setContactError] = useState("");
  async function submitContact(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setContactState("sending");
    setContactError("");
    try {
      const response = await fetch("/api/contact", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(contact)
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result.ok) throw new Error(response.status === 429 ? "Aguarde alguns instantes antes de enviar outra mensagem." : "Não foi possível enviar agora. Tente novamente mais tarde.");
      setContact({ name: "", email: "", subject: "", message: "", website: "" });
      setContactState("sent");
    } catch (error) {
      setContactError(error instanceof Error ? error.message : "Falha ao enviar mensagem.");
      setContactState("error");
    }
  }
  const [filter, setFilter] = useState<(typeof filters)[number]>("Todos");
  const [selected, setSelected] = useState<string | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const modalCloseRef = useRef<HTMLButtonElement>(null);
  const reduceMotion = useReducedMotion();
  const visible = useMemo(() => filter === "Todos" ? publicUniverses : publicUniverses.filter((item) => item.category === filter), [filter, publicUniverses]);
  const active = publicUniverses.find((item) => item.id === selected);

  useEffect(() => {
    if (!menuOpen) return;
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") setMenuOpen(false);
    };
    window.addEventListener("keydown", closeOnEscape);
    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [menuOpen]);

  useEffect(() => {
    if (!selected) return;
    const previousOverflow = document.body.style.overflow;
    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    document.body.style.overflow = "hidden";
    modalCloseRef.current?.focus();
    const handleDialogKeys = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setSelected(null);
        return;
      }
      if (event.key !== "Tab") return;
      const dialog = document.querySelector<HTMLElement>(".detail-modal");
      const focusable = dialog?.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
      if (!focusable?.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    window.addEventListener("keydown", handleDialogKeys);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", handleDialogKeys);
      previouslyFocused?.focus();
    };
  }, [selected]);

  return <div className="site-shell">
    <header className="site-header">
      <a className="wordmark" href="#inicio" aria-label="FIXXAR GROUP — início">FIXXAR<span>GROUP</span><i>X</i></a>
      <button className="menu-toggle" type="button" aria-label={menuOpen ? "Fechar menu" : "Abrir menu"} aria-expanded={menuOpen} aria-controls="primary-navigation" onClick={() => setMenuOpen(!menuOpen)}>{menuOpen ? <X /> : <Menu />}</button>
      <nav id="primary-navigation" className={menuOpen ? "main-nav is-open" : "main-nav"} aria-label="Navegação principal">
        <a href="#grupo" onClick={() => setMenuOpen(false)}>O grupo</a><a href="#universos" onClick={() => setMenuOpen(false)}>Universos</a><a href="#processo" onClick={() => setMenuOpen(false)}>Como fazemos</a><a href="#essencia" onClick={() => setMenuOpen(false)}>Nossa essência</a><a className="nav-contact" href="#conexoes" onClick={() => setMenuOpen(false)}>Conecte-se <ArrowUpRight size={15} /></a>
      </nav>
    </header>

    <main id="conteudo">
      <section className="hero" id="inicio">
        <div className="hero-grain" />
        <div className="hero-orbit orbit-one" /><div className="hero-orbit orbit-two" />
        <div className="hero-content">
          <motion.p className="eyebrow" initial={reduceMotion ? false : { opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: .7 }}>UM ECOSSISTEMA DE IDEIAS, MARCAS E POSSIBILIDADES</motion.p>
          <motion.h1 initial={reduceMotion ? false : { opacity: 0, y: 24 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: .8, delay: .12 }}>{portalCopy.headline}<br /><em>{portalCopy.subheadline}</em></motion.h1>
          <p className="hero-copy">{portalCopy.intro}</p>
          <a className="text-link" href="#universos">Explore nossos universos <ArrowDownRight size={18} /></a>
        </div>
        <div className="hero-art" aria-hidden="true">
          <div className="hero-art-ring ring-a" />
          <div className="hero-art-ring ring-b" />
          <div className="hero-art-ring ring-c" />
          <div className="hero-art-core"><span>F</span><b>×</b></div>
          <div className="hero-art-label label-top">IDEAS INTO FORM</div>
          <div className="hero-art-label label-bottom">BRANDS WITH PURPOSE</div>
          <div className="hero-art-node node-one" />
          <div className="hero-art-node node-two" />
        </div>
        <div className="hero-index"><span>01 / 05</span><span>CRIAR · CONECTAR · TRANSFORMAR</span></div>
      </section>

      <section className="manifesto section-pad" id="grupo">
        <div className="section-kicker"><span>01</span><span>O GRUPO</span></div>
        <div className="manifesto-grid"><h2>{portalCopy.groupHeadline}</h2><div className="manifesto-text"><p className="lead">{portalCopy.groupLead}</p><p>{portalCopy.groupBody}</p><a className="text-link dark-link" href="#essencia">Conheça nossa essência <ArrowRight size={17} /></a></div></div>
      </section>

      <section className="universes section-pad" id="universos">
        <div className="section-kicker light-kicker"><span>02</span><span>NOSSOS UNIVERSOS</span></div>
        <div className="section-heading"><div><p className="eyebrow">DIFERENTES ESPECIALIDADES. UMA VISÃO CONECTADA.</p><h2>Conheça nossas<br /><em>marcas.</em></h2></div><p>Cada universo tem sua identidade, sua linguagem e seu jeito de transformar. Todos fazem parte de algo maior.</p></div>
        <div className="filter-list" role="group" aria-label="Filtrar universos">{filters.map((item) => <button key={item} className={filter === item ? "filter active" : "filter"} onClick={() => setFilter(item)} aria-pressed={filter === item}>{item}</button>)}</div>
        <p className="results-count" role="status" aria-live="polite">{visible.length} {visible.length === 1 ? "universo encontrado" : "universos encontrados"}</p>
        <div className="universe-grid" aria-live="polite">{visible.map((item, index) => <motion.article layout key={item.id} className="universe-card" initial={reduceMotion ? false : { opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: .4, delay: index * .05 }}>
          <div className="card-top"><span>0{publicUniverses.findIndex((u) => u.id === item.id) + 1}</span><span>{item.category}</span></div><UniverseArtwork id={item.id} /><p className="card-eyebrow">{item.eyebrow}</p><h3>{item.name}</h3><p className="card-description">{item.description}</p><button className="card-action" aria-haspopup="dialog" aria-label={`Conheça ${item.name}`} onClick={() => setSelected(item.id)}>{item.status === "active" ? "Explore este universo" : "Conheça este universo"} <ArrowUpRight size={17} /></button>
        </motion.article>)}</div>
      </section>

      <section className="process section-pad" id="processo">
        <div className="section-kicker"><span>03</span><span>DO PENSAMENTO À REALIDADE</span></div>
        <div className="process-intro"><h2>{portalCopy.processHeadline}</h2><p>{portalCopy.processIntro}</p></div>
        <div className="process-track">{portalCopy.processSteps.map((step,index)=><article key={index}><span>0{index+1}</span><h3>{step.title}</h3><p>{step.text}</p></article>)}</div>
      </section>

      <section className="essence section-pad" id="essencia">
        <div className="section-kicker"><span>04</span><span>NO QUE ACREDITAMOS</span></div><div className="section-heading essence-heading"><h2>Quatro pilares.<br /><em>Uma essência.</em></h2><p>O que fazemos muda de forma. O que nos move permanece.</p></div>
        <div className="pillar-grid">{pillars.map((pillar) => <article className="pillar" key={pillar.number}><span>{pillar.number}</span><h3>{pillar.title}</h3><p>{pillar.text}</p></article>)}</div>
      </section>

      <section className="closing section-pad" id="conexoes"><p className="eyebrow">FIXXAR GROUP</p><h2>Vamos dar forma<br />à próxima <em>ideia?</em></h2><p>Conte um pouco sobre o que você deseja construir. Nossa equipe poderá conhecer sua proposta.</p><form className="contact-form" onSubmit={submitContact}>
      <div className="contact-fields"><label>Seu nome<input required maxLength={160} autoComplete="name" value={contact.name} onChange={e=>setContact({...contact,name:e.target.value})} /></label><label>Seu e-mail<input required type="email" maxLength={190} autoComplete="email" value={contact.email} onChange={e=>setContact({...contact,email:e.target.value})} /></label></div>
      <label>Assunto<input required maxLength={180} value={contact.subject} onChange={e=>setContact({...contact,subject:e.target.value})} /></label>
      <label>Mensagem<textarea required maxLength={5000} rows={5} value={contact.message} onChange={e=>setContact({...contact,message:e.target.value})} /></label>
      <label className="contact-honeypot" aria-hidden="true" tabIndex={-1}>Website<input tabIndex={-1} autoComplete="off" value={contact.website} onChange={e=>setContact({...contact,website:e.target.value})} /></label>
      <button className="contact-submit" type="submit" disabled={contactState==="sending"}>{contactState==="sending"?"Enviando…":"Enviar mensagem"} <ArrowUpRight size={17}/></button>
      {contactState==="sent"&&<p className="contact-feedback" role="status">Mensagem recebida. Obrigado por entrar em contato.</p>}
      {contactState==="error"&&<p className="contact-feedback is-error" role="alert">{contactError}</p>}
    </form></section>
    </main>

    <footer className="site-footer"><a className="wordmark footer-mark" href="#inicio">FIXXAR<span>GROUP</span><i>X</i></a><p>Ideias que ganham forma. Marcas que deixam marcas.</p><span>© {new Date().getFullYear()} FIXXAR GROUP</span></footer>

    {active && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelected(null); }}><section className="detail-modal" role="dialog" aria-modal="true" aria-labelledby="detail-title" aria-describedby="detail-description"><button ref={modalCloseRef} className="modal-close" onClick={() => setSelected(null)} aria-label="Fechar janela"><X /></button><UniverseArtwork id={active.id} compact /><p className="eyebrow">{active.category} · {active.eyebrow}</p><h2 id="detail-title">{active.name}</h2><p className="detail-lead" id="detail-description">{active.description}</p><div className="detail-focus"><span className="detail-focus-label">O QUE DEFINE ESTE UNIVERSO</span><p>{active.detail}</p></div>{active.status === "active" && active.url ? <a className="text-link" href={active.url} target="_blank" rel="noopener noreferrer">Visitar site oficial <ArrowUpRight size={17} /></a> : <span className="status-note">Este universo está em desenvolvimento. Novidades em breve.</span>}</section></div>}
  </div>;
}

export default function App() {
  return (window.location.pathname === "/painel" || window.location.pathname === "/painel/") ? <Admin /> : <PublicPortal />;
}
