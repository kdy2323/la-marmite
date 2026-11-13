(() => {
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    const sansAccents = (s) => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    /* ---------- Menu mobile ---------- */
    const boutonMenu = $('.menu-bouton');
    boutonMenu?.addEventListener('click', () => {
        const ouvert = boutonMenu.getAttribute('aria-expanded') === 'true';
        boutonMenu.setAttribute('aria-expanded', String(!ouvert));
        $('#nav').classList.toggle('nav--ouverte', !ouvert);
    });

    /* ---------- Planning : fréquence → jours répartis ---------- */
    const composeur = $('#composeur');
    if (composeur) {
        const cases = $$('input[name="jours[]"]', composeur);
        const boutons = $$('.frequence__btn', composeur);
        const marquer = (n) => boutons.forEach((b) => b.setAttribute('aria-checked', String(Number(b.dataset.n) === n)));

        boutons.forEach((b) => b.addEventListener('click', () => {
            const n = Number(b.dataset.n);
            const choisis = new Set();
            for (let i = 0; i < n; i++) choisis.add(JOURS[Math.round((i * 7) / n)]);
            cases.forEach((c) => (c.checked = choisis.has(c.value)));
            marquer(n);
        }));
        cases.forEach((c) => c.addEventListener('change', () => marquer(cases.filter((x) => x.checked).length)));

        /* Régions : « Tout le Cameroun » exclut les choix région par région */
        const toutes = $('input[name="toutes_regions"]', composeur);
        const regions = $$('input[name="regions[]"]', composeur);
        const info = $('#regions-info');
        const total = regions.reduce((n, r) => n + Number(r.dataset.nb), 0);
        const majInfo = () => {
            const choisies = regions.filter((r) => r.checked);
            const nb = choisies.length ? choisies.reduce((n, r) => n + Number(r.dataset.nb), 0) : total;
            info.textContent = `${nb} plat${nb > 1 ? 's' : ''} dans votre sélection.`;
        };
        toutes?.addEventListener('change', () => {
            if (toutes.checked) regions.forEach((r) => (r.checked = false));
            else if (!regions.some((r) => r.checked)) toutes.checked = true; // il faut au moins un choix
            majInfo();
        });
        regions.forEach((r) => r.addEventListener('change', () => {
            toutes.checked = !regions.some((x) => x.checked);
            majInfo();
        }));
        if (toutes) majInfo();
    }

    /* ---------- Recettes : filtre instantané ---------- */
    const liste = $('#liste-recettes');
    if (liste) {
        const recherche = $('#recherche');
        const region = $('#filtre-region');
        const lignes = $$('li', liste);
        const filtrer = () => {
            const q = sansAccents(recherche.value.trim());
            let visibles = 0;
            lignes.forEach((li) => {
                const ok = (!q || sansAccents(li.dataset.texte).includes(q)) && (!region.value || li.dataset.region === region.value);
                li.hidden = !ok;
                visibles += ok;
            });
            $('#aucun-resultat').hidden = visibles > 0;
        };
        recherche.addEventListener('input', filtrer);
        region.addEventListener('change', filtrer);
        recherche.form.addEventListener('submit', (e) => e.preventDefault());
        filtrer();
    }

    /* ---------- Recette : ajuster les portions ---------- */
    const portions = $('.portions');
    if (portions) {
        const base = Number(portions.dataset.base);
        let n = base;
        const PLURIELS = { gousse: 'gousses', branche: 'branches', botte: 'bottes', paquet: 'paquets', 'boîte': 'boîtes', sachet: 'sachets', morceau: 'morceaux', 'épi': 'épis' };
        const formater = (v, u) => {
            if (u === 'g' && v >= 1000) return [v / 1000, 'kg'];
            if (u === 'cl' && v >= 100) return [v / 100, 'L'];
            if (u === 'pièce') return [v, ''];
            return [v, v >= 2 ? (PLURIELS[u] || u) : u];
        };
        const nombre = (v, u) => {
            const arrondi = ['g', 'cl'].includes(u) ? Math.round(v / 5) * 5 : Math.round(v * 10) / 10;
            return String(arrondi).replace('.', ',');
        };
        const maj = () => {
            $('.portions__valeur span', portions).textContent = n;
            $$('.qte[data-base]').forEach((el) => {
                const brut = Number(el.dataset.base) * n / base;
                const [v, u] = formater(brut, el.dataset.unite);
                el.textContent = `${nombre(v, ['g', 'cl'].includes(u) ? u : '')} ${u}`.trim();
            });
        };
        $$('.portions__btn', portions).forEach((b) => b.addEventListener('click', () => {
            n = Math.min(20, Math.max(1, n + Number(b.dataset.delta)));
            maj();
        }));
    }

    /* ---------- Courses : cases cochées mémorisées + copier ---------- */
    const courses = $('.courses');
    if (courses) {
        const cle = courses.dataset.cle;
        let coches = {};
        try { coches = JSON.parse(localStorage.getItem(cle) || '{}'); } catch { coches = {}; }

        $$('input[type="checkbox"]', courses).forEach((c) => {
            c.checked = Boolean(coches[c.dataset.ref]);
            c.addEventListener('change', () => {
                coches[c.dataset.ref] = c.checked;
                try { localStorage.setItem(cle, JSON.stringify(coches)); } catch { /* stockage indisponible */ }
            });
        });

        $('#btn-copier')?.addEventListener('click', async (e) => {
            const lignes = [];
            $$('.rayon', courses).forEach((r) => {
                lignes.push(`\n${$('h2', r).textContent.toUpperCase()}`);
                $$('.article', r).forEach((a) => {
                    if ($('input', a).checked) return;
                    const nom = $('.article__nom', a).firstChild.textContent.trim();
                    lignes.push(`- ${nom} : ${$('.article__qte', a).textContent}`);
                });
            });
            const texte = `Liste de courses${lignes.join('\n')}`;
            try {
                await navigator.clipboard.writeText(texte);
                e.target.textContent = 'Liste copiée';
                setTimeout(() => (e.target.textContent = 'Copier la liste'), 2000);
            } catch {
                prompt('Copiez la liste :', texte);
            }
        });
    }
})();
