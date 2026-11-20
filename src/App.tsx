import { useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import { ArrowDownRight, ArrowRight, ArrowUpRight, Menu, X } from "lucide-react";
import { motion, useReducedMotion } from "framer-motion";
import { pillars, universes } from "./data";
import Admin from "./Admin";
import InstitutionalPage from "./InstitutionalPage";

const filters = ["Todos", "Estratégia", "Imagem", "Editorial", "Infantil", "Personalização", "Tecnologia"] as const;

const officialBrandLogos: Record<string,string> = { agencia: "/brand-logos/agencia-digital.png", fotografia: "/brand-logos/thiago-santos-estudio.png", editora: "/brand-logos/entre-o-ceu-e-papel.png", "tio-moises": "/brand-logos/falai-tio-moises.png", brindes: "/brand-logos/brindes.png", "st-seg": "/brand-logos/st-seg.png", "yad-sistemas-integrados": "/brand-logos/yad.png", "yad": "/brand-logos/yad.png" };


function UniverseArtwork({ id, compact = false }: { id: string; compact?: boolean }) {
  return <div className={`universe-artwork artwork-${id}${compact ? " is-compact" : ""}`} aria-hidden="true">
    <span className="art-shape shape-one" /><span className="art-shape shape-two" /><span className="art-shape shape-three" />
    <span className="art-monogram">{id === "agencia" ? "F/" : id === "fotografia" ? "◉" : id === "editora" ? "E" : id === "tio-moises" ? "✳" : id === "yad-sistemas-integrados" ? "Y" : "□"}</span>
    <span className="art-caption">{id === "agencia" ? "IDEAS / STRATEGY" : id === "fotografia" ? "LIGHT / FRAME" : id === "editora" ? "PAPER / STORIES" : id === "tio-moises" ? "PLAY / LEARN" : id === "yad-sistemas-integrados" ? "SYSTEMS / INTEGRATION" : "OBJECT / FORM"}</span>
  </div>;
}

function PublicPortal() {
  const [contact, setContact] = useState({ name: "", email: "", subject: "", message: "", website: "" });
  const [publicUniverses, setPublicUniverses] = useState(universes);
  const [publishedPages, setPublishedPages] = useState<Array<{slug:string;title:string}>>([]);
  const [seoMeta,setSeoMeta]=useState({title:"FIXXAR GROUP — Ideias que ganham forma",description:"Criatividade, comunicação, conhecimento e inovação em um ecossistema de marcas."});
  const [maintenanceActive,setMaintenanceActive]=useState(false);
  const [publicSettings,setPublicSettings]=useState<Record<string,string>>({ contact_email:"thiago@fixxarestudios.com.br", contact_phone:"+5524998212820", contact_address:"Volta Redonda - RJ, Brasil", whatsapp_url:"https://wa.me/5524998212820?text=Ol%C3%A1!%20Vim%20pelo%20site%20da%20FIXXAR%20GROUP%20e%20gostaria%20de%20mais%20informa%C3%A7%C3%B5es." });
  const [portalCopy, setPortalCopy] = useState({ headline: "Ideias que ganham forma.", subheadline: "Marcas que deixam marcas.", intro: "Somos um grupo que conecta criatividade, comunicação, conhecimento e inovação para transformar ideias em experiências com significado.", groupHeadline: "Um grupo. Muitas formas de transformar.", groupLead: "A FIXXAR GROUP reúne diferentes competências em um ecossistema que transforma ideias em projetos, marcas e experiências.", groupBody: "Atuamos em universos distintos, mas conectados por uma mesma visão: fazer bem-feito, com propósito, consistência e atenção aos detalhes.", processHeadline: "O movimento por trás da ideia.", processIntro: "Uma ideia merece mais do que existir. Ela merece encontrar seu caminho, ganhar consistência e chegar às pessoas.", universeEyebrow: "DIFERENTES ESPECIALIDADES. UMA VISÃO CONECTADA.", universeHeadline: "Conheça nossas", universeAccent: "marcas.", universeIntro: "Cada universo tem sua identidade, sua linguagem e seu jeito de transformar. Todos fazem parte de algo maior.", essenceHeadline: "Quatro pilares.", essenceAccent: "Uma essência.", essenceIntro: "O que fazemos muda de forma. O que nos move permanece.", closingHeadline: "Vamos dar forma", closingAccent: "à próxima ideia?", closingIntro: "Conte um pouco sobre o que você deseja construir. Nossa equipe poderá conhecer sua proposta.", processSteps: [{title:"Imaginar",text:"Enxergar possibilidades e descobrir o que ainda pode existir."},{title:"Construir",text:"Dar estrutura, linguagem e intenção para cada projeto."},{title:"Conectar",text:"Aproximar ideias, pessoas e experiências que fazem sentido."},{title:"Transformar",text:"Levar o que foi imaginado para o mundo real."}] });
  useEffect(() => {
    let active = true;
    fetch("/api/public/content", { headers: { Accept: "application/json" }, credentials: "same-origin" })
      .then(response => { if (!response.ok) throw new Error("Public content unavailable"); return response.json(); })
      .then((data: { page?: { content?: Partial<typeof portalCopy> } | null; seo?: { title?: string; description?: string }; maintenance?: boolean; settings?: Record<string,string>; pages?: Array<{slug:string;title:string}>; universes?: Array<{ slug: string; name: string; eyebrow: string; description: string; detail: string; category: string; website_url?: string | null; status: string; sort_order?:number; logo_url?:string|null; logo_background?:"transparent"|"black"|"white"|"brand"|null; cover_image_url?:string|null; contact_email?:string|null; contact_phone?:string|null; whatsapp_url?:string|null; instagram_url?:string|null; facebook_url?:string|null; youtube_url?:string|null; linkedin_url?:string|null; contact_address?:string|null }> }) => {
        if (!active) return;
        if (data.page?.content) setPortalCopy(current => ({ ...current, ...data.page?.content }));
        if (data.seo) setSeoMeta(current => ({title:data.seo?.title || current.title,description:data.seo?.description || current.description}));
        setMaintenanceActive(data.maintenance === true);
        setPublicSettings({ contact_email:"thiago@fixxarestudios.com.br", contact_phone:"+5524998212820", contact_address:"Volta Redonda - RJ, Brasil", whatsapp_url:"https://wa.me/5524998212820?text=Ol%C3%A1!%20Vim%20pelo%20site%20da%20FIXXAR%20GROUP%20e%20gostaria%20de%20mais%20informa%C3%A7%C3%B5es.", ...(data.settings || {}) });
        setPublishedPages(data.pages || []);
        if (data.universes?.length) setPublicUniverses(data.universes.map(item => ({ id: item.slug, name: item.name, eyebrow: item.eyebrow, description: item.description, detail: item.detail, category: item.category as (typeof universes)[number]["category"], url: item.website_url || undefined, status: item.status === "archived" ? "archived" : item.status === "active" ? "active" : "in-development", sortOrder:item.sort_order,logoUrl:item.logo_url||officialBrandLogos[item.slug]||undefined,logoBackground:item.logo_background||undefined,coverImageUrl:item.cover_image_url||undefined,contactEmail:item.contact_email||undefined,contactPhone:item.contact_phone||undefined,whatsappUrl:item.whatsapp_url||undefined,instagramUrl:item.instagram_url||undefined,facebookUrl:item.facebook_url||undefined,youtubeUrl:item.youtube_url||undefined,linkedinUrl:item.linkedin_url||undefined,address:item.contact_address||undefined })));
      }).catch(() => { /* Static content remains available when the API is not configured. */ });
    return () => { active = false; };
  }, []);
  useEffect(() => {
    document.title = seoMeta.title;
    let description = document.querySelector<HTMLMetaElement>('meta[name="description"]');
    if (!description) { description = document.createElement("meta"); description.name = "description"; document.head.appendChild(description); }
    description.content = seoMeta.description;
    const setProperty = (property:string, content:string) => {
      let tag = document.querySelector<HTMLMetaElement>(`meta[property="${property}"]`);
      if (!tag) { tag = document.createElement("meta"); tag.setAttribute("property",property); document.head.appendChild(tag); }
      tag.content = content;
    };
    setProperty("og:title",seoMeta.title);
    setProperty("og:description",seoMeta.description);
    setProperty("og:type","website");
  }, [seoMeta]);
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
  useEffect(() => {
    if (reduceMotion) return;
    const targets = document.querySelectorAll<HTMLElement>(".section-kicker, .manifesto-grid, .section-heading, .filter-list, .universe-card, .process-intro, .process-track article, .pillar, .contact-form, .hero-copy, .hero .text-link, .hero-index, .closing h2, .closing > p:not(.eyebrow)");
    targets.forEach(element => element.classList.add("scroll-reveal"));
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add("is-visible"); observer.unobserve(entry.target); } });
    }, { threshold: 0.14, rootMargin: "0px 0px -45px 0px" });
    targets.forEach(element => observer.observe(element));
    return () => observer.disconnect();
  }, [reduceMotion]);
  const visible = useMemo(() => filter === "Todos" ? publicUniverses : publicUniverses.filter((item) => item.category === filter), [filter, publicUniverses]);
  const active = publicUniverses.find((item) => item.id === selected);
  const [showTop,setShowTop] = useState(false);
  useEffect(() => { const onScroll=()=>setShowTop(window.scrollY>420); window.addEventListener("scroll",onScroll,{passive:true}); onScroll(); return ()=>window.removeEventListener("scroll",onScroll); }, []);

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

  if (maintenanceActive) return <main className="maintenance-screen"><div className="maintenance-mark">FIXXAR<span>GROUP</span><i>×</i></div><span className="maintenance-kicker">ESTAMOS APRIMORANDO NOSSO ESPAÇO</span><h1>Voltamos <em>em breve.</em></h1><p>Estamos realizando melhorias para oferecer uma experiência ainda melhor. Agradecemos a compreensão.</p><span className="maintenance-rule" /><small>FIXXAR GROUP · IDEIAS QUE GANHAM FORMA</small></main>;

  return <div className="site-shell">
    <header className="site-header">
      <a className="wordmark" href="#inicio" aria-label="FIXXAR GROUP — início">FIXXAR<span>GROUP</span><i>X</i></a>
      <button className="menu-toggle" type="button" aria-label={menuOpen ? "Fechar menu" : "Abrir menu"} aria-expanded={menuOpen} aria-controls="primary-navigation" onClick={() => setMenuOpen(!menuOpen)}>{menuOpen ? <X /> : <Menu />}</button>
      <nav id="primary-navigation" className={menuOpen ? "main-nav is-open" : "main-nav"} aria-label="Navegação principal">
        <a href="#grupo" onClick={() => setMenuOpen(false)}>O grupo</a><a href="#universos" onClick={() => setMenuOpen(false)}>Universos</a><a href="#processo" onClick={() => setMenuOpen(false)}>Como fazemos</a><a href="#essencia" onClick={() => setMenuOpen(false)}>Nossa essência</a>{publishedPages.map(page => <a key={page.slug} href={"/" + page.slug} onClick={() => setMenuOpen(false)}>{page.title}</a>)}<a className="nav-contact" href="#conexoes" onClick={() => setMenuOpen(false)}>Conecte-se <ArrowUpRight size={15} /></a>
      </nav>
    </header>

    <main id="conteudo" style={{["--body-copy-size" as string]: `${Math.max(14, Math.min(22, Number(publicSettings.body_text_size) || 14))}px` } as React.CSSProperties}>
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
          <div className="hero-art-core"><img className="hero-brand-logo" src="/fixxar-group-logo.svg" alt="FIXXAR GROUP" /></div>
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
        <div className="section-heading"><div><p className="eyebrow">{portalCopy.universeEyebrow}</p><h2>{portalCopy.universeHeadline}<br /><em>{portalCopy.universeAccent}</em></h2></div><p className="universe-highlight">{portalCopy.universeIntro}</p></div>
        <div className="filter-list" role="group" aria-label="Filtrar universos">{filters.map((item) => <button key={item} className={filter === item ? "filter active" : "filter"} onClick={() => setFilter(item)} aria-pressed={filter === item}>{item}</button>)}</div>
        <p className="results-count" role="status" aria-live="polite">{visible.length} {visible.length === 1 ? "universo encontrado" : "universos encontrados"}</p>
        <div className="universe-grid" aria-live="polite">{visible.map((item, index) => <motion.article layout key={item.id} className="universe-card" initial={reduceMotion ? false : { opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: .4, delay: index * .05 }}>
          <div className="card-top"><span>0{publicUniverses.findIndex((u) => u.id === item.id) + 1}</span><span>{item.category}</span></div>{item.logoUrl ? <div className={`brand-logo-frame logo-bg-${item.logoBackground||"transparent"}`}><img src={item.logoUrl} alt={`Logotipo ${item.name}`} loading="lazy"/></div> : <UniverseArtwork id={item.id} />}<p className="card-eyebrow">{item.eyebrow}</p><h3>{item.name}</h3><p className="card-description">{item.description}</p><button className="card-action" aria-haspopup="dialog" aria-label={`Conheça ${item.name}`} onClick={() => setSelected(item.id)}>{item.status === "active" ? "Explore este universo" : "Conheça este universo"} <ArrowUpRight size={17} /></button>
        </motion.article>)}</div>
      </section>

      <section className="process section-pad" id="processo">
        <div className="section-kicker"><span>03</span><span>DO PENSAMENTO À REALIDADE</span></div>
        <div className="process-intro"><h2>{portalCopy.processHeadline}</h2><p>{portalCopy.processIntro}</p></div>
        <div className="process-track">{portalCopy.processSteps.map((step,index)=><article key={index}><span>0{index+1}</span><h3>{step.title}</h3><p>{step.text}</p></article>)}</div>
      </section>

      <section className="essence section-pad" id="essencia">
        <div className="section-kicker"><span>04</span><span>NO QUE ACREDITAMOS</span></div><div className="section-heading essence-heading"><h2>{portalCopy.essenceHeadline}<br /><em>{portalCopy.essenceAccent}</em></h2><p>{portalCopy.essenceIntro}</p></div>
        <div className="pillar-grid">{pillars.map((pillar) => <article className="pillar" key={pillar.number}><span>{pillar.number}</span><h3>{pillar.title}</h3><p>{pillar.text}</p></article>)}</div>
      </section>

      <section className="closing section-pad" id="conexoes"><p className="eyebrow">FIXXAR GROUP</p><h2>{portalCopy.closingHeadline}<br /><em>{portalCopy.closingAccent}</em></h2><p>{portalCopy.closingIntro}</p><form className="contact-form" onSubmit={submitContact}>
      <div className="contact-fields"><label>Seu nome<input required maxLength={160} autoComplete="name" value={contact.name} onChange={e=>setContact({...contact,name:e.target.value})} /></label><label>Seu e-mail<input required type="email" maxLength={190} autoComplete="email" value={contact.email} onChange={e=>setContact({...contact,email:e.target.value})} /></label></div>
      <label>Assunto<input required maxLength={180} value={contact.subject} onChange={e=>setContact({...contact,subject:e.target.value})} /></label>
      <label>Mensagem<textarea required maxLength={5000} rows={5} value={contact.message} onChange={e=>setContact({...contact,message:e.target.value})} /></label>
      <label className="contact-honeypot" aria-hidden="true" tabIndex={-1}>Website<input tabIndex={-1} autoComplete="off" value={contact.website} onChange={e=>setContact({...contact,website:e.target.value})} /></label>
      <button className="contact-submit" type="submit" disabled={contactState==="sending"}>{contactState==="sending"?"Enviando…":"Enviar mensagem"} <ArrowUpRight size={17}/></button>
      {contactState==="sent"&&<p className="contact-feedback" role="status">Mensagem recebida. Obrigado por entrar em contato.</p>}
      {contactState==="error"&&<p className="contact-feedback is-error" role="alert">{contactError}</p>}
    </form></section>
    </main>

    <footer className="site-footer"><a className="wordmark footer-mark" href="#inicio">FIXXAR<span>GROUP</span><i>X</i></a><p>{publicSettings.footer_text || "Ideias que ganham forma. Marcas que deixam marcas."}</p><span>© {new Date().getFullYear()} FIXXAR GROUP</span><p className="site-credit">Desenvolvido por <a href="https://agencia.fixxarestudios.com.br/" target="_blank" rel="noopener noreferrer">Fixxar Agência Digital</a></p></footer>

    <a className="floating-whatsapp" href={publicSettings.whatsapp_url || "https://wa.me/5524998212820"} target="_blank" rel="noopener noreferrer" aria-label="Fale conosco pelo WhatsApp" title="WhatsApp" data-tooltip="WhatsApp">
      <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16.02 3.2A12.72 12.72 0 0 0 5.1 22.44L3.4 28.7l6.42-1.68A12.75 12.75 0 1 0 16.02 3.2Zm0 23.2c-2 0-3.96-.54-5.67-1.57l-.4-.24-3.8.99 1.02-3.7-.26-.42A10.42 10.42 0 1 1 16.02 26.4Zm5.72-7.8c-.31-.16-1.84-.91-2.13-1.01-.29-.1-.5-.16-.71.16-.21.31-.81 1.01-.99 1.22-.18.21-.36.24-.67.08-.31-.16-1.31-.48-2.5-1.54-.92-.82-1.54-1.83-1.72-2.14-.18-.31-.02-.48.14-.64.14-.14.31-.36.47-.54.16-.18.21-.31.31-.52.1-.21.05-.39-.03-.55-.08-.16-.71-1.7-.97-2.33-.25-.61-.51-.53-.71-.54h-.61c-.21 0-.55.08-.84.39-.29.31-1.1 1.07-1.1 2.61s1.13 3.03 1.28 3.24c.16.21 2.22 3.39 5.38 4.75.75.32 1.34.51 1.8.65.76.24 1.45.21 2 .13.61-.09 1.84-.75 2.1-1.48.26-.73.26-1.36.18-1.48-.08-.13-.29-.21-.6-.37Z"/></svg>
    </a>
    {showTop && <button className="back-to-top" type="button" onClick={()=>window.scrollTo({top:0,behavior:"smooth"})} aria-label="Voltar ao topo" title="Voltar ao topo">↑</button>}
    {active && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelected(null); }}><section className="detail-modal" role="dialog" aria-modal="true" aria-labelledby="detail-title" aria-describedby="detail-description"><button ref={modalCloseRef} className="modal-close" onClick={() => setSelected(null)} aria-label="Fechar janela"><X /></button>{active.coverImageUrl && <img className="brand-cover-image" src={active.coverImageUrl} alt={`Apresentação de ${active.name}`}/>}<div className="brand-modal-heading">{active.logoUrl && <img className="brand-logo-image" src={active.logoUrl} alt={`Logotipo ${active.name}`}/>}<UniverseArtwork id={active.id} compact /><p className="eyebrow">{active.category} · {active.eyebrow}</p></div><h2 id="detail-title">{active.name}</h2><p className="detail-lead" id="detail-description">{active.description}</p><div className="detail-focus"><span className="detail-focus-label">O QUE DEFINE ESTE UNIVERSO</span><p>{active.detail}</p></div><div className="brand-contact-links">{active.contactEmail && <a href={`mailto:${active.contactEmail}`}>{active.contactEmail}</a>}{active.contactPhone && <a href={`tel:${active.contactPhone.replace(/[^+0-9]/g,"")}`}>{active.contactPhone}</a>}{active.address && <span>{active.address}</span>}{[["WhatsApp",active.whatsappUrl],["Instagram",active.instagramUrl],["Facebook",active.facebookUrl],["YouTube",active.youtubeUrl],["LinkedIn",active.linkedinUrl]].map(([label,url])=>url?<a key={label} href={url} target="_blank" rel="noopener noreferrer">{label}</a>:null)}</div>{active.status === "active" && active.url ? <a className="text-link" href={active.url} target="_blank" rel="noopener noreferrer">Visitar site oficial <ArrowUpRight size={17} /></a> : <span className="status-note">Este universo está em desenvolvimento. Novidades em breve.</span>}</section></div>}
  </div>;
}

export default function App() {
  const path = window.location.pathname.split("/").filter(Boolean).join("/");
  if (path === "painel") return <Admin />;
  if (path) return <InstitutionalPage slug={path} />;
  return <PublicPortal />;
}
