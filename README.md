# Remove IDs from URL

Modulo per PrestaShop che rimuove gli ID numerici dagli URL di prodotti, categorie, marche, fornitori e pagine CMS.

| Prima | Dopo |
|---|---|
| `/12-scarpe/345-scarpa-rossa.html` | `/scarpa-rossa.html` |
| `/12-scarpe` | `/scarpe` |
| `/brand/3-nike` | `/brand/nike` |

> ⚠️ **Stato: sperimentale.** Il modulo modifica le route del negozio e installa un override del core (`Dispatcher`). Non è stato testato su un'installazione reale di PrestaShop. Provalo sempre su un ambiente di staging, con backup di database e file, prima di usarlo in produzione.

## Compatibilità

- PrestaShop 1.7.x e 8.x (da verificare sulla tua versione)
- URL amichevoli attivi (Parametri del negozio → Traffico e SEO)

## Cosa fa

- Imposta le route senza `{id}` scrivendole direttamente in configurazione, perché il back office rifiuta regole prive della parola chiave `{id}`:

  | Elemento | Route |
  |---|---|
  | Prodotto | `{rewrite}.html` |
  | Categoria | `{rewrite}` |
  | Marca | `brand/{rewrite}` |
  | Fornitore | `supplier/{rewrite}` |
  | Pagina CMS | `content/{rewrite}` |
  | Categoria CMS | `content/category/{rewrite}` |

- Installa un override di `Dispatcher` che individua l'elemento a partire dal rewrite. Se non esiste, mostra la pagina 404.
- Reindirizza con **301** i vecchi URL con ID verso i nuovi. Il redirect scatta solo se il rewrite coincide con quello dell'elemento, così un URL come `500-ml-bottiglia.html` non viene scambiato per un vecchio URL con ID.
- Alla disinstallazione ripristina le route originali.
- Nella pagina di configurazione segnala i **rewrite duplicati**, che senza ID entrerebbero in conflitto.

## Installazione

1. Fai un backup di database e file.
2. Assicurati che la cartella si chiami esattamente `removeidsfromurl` e crea lo zip con quella cartella alla radice.
3. In back office vai su **Moduli → Gestione moduli → Carica un modulo**.
4. Controlla che gli **URL amichevoli** siano attivi.
5. Svuota la cache da **Parametri avanzati → Prestazioni**.
6. Apri la configurazione del modulo e correggi gli eventuali rewrite duplicati.

## Test consigliati dopo l'installazione

- Apri un prodotto, una categoria, una marca, un fornitore e una pagina CMS, in tutte le lingue attive.
- Apri un vecchio URL con ID e verifica che faccia un 301 verso il nuovo, senza loop.
- Controlla carrello, checkout, pagine come "contatti" e i moduli di terze parti.
- Controlla Google Search Console per eventuali 404 nei giorni successivi.

## Disinstallazione

Dalla gestione moduli disinstalla il modulo: le route vengono ripristinate ai valori salvati all'installazione e l'override viene rimosso. Svuota poi la cache.

## Limiti noti

- In **multistore** le route sono scritte a livello globale: eventuali valori impostati per singolo negozio le sovrascrivono.
- Il redirect di un URL prodotto con combinazione (`12-34-nome.html`) porta all'URL del prodotto senza la combinazione.
- La route categoria `{rewrite}` è molto "avida": gli URL che non corrispondono a nulla finiscono in 404 tramite l'override.
- Può andare in conflitto con altri override di `Dispatcher` o con moduli SEO e multilingua che modificano le route.
- Senza ID, due elementi con lo stesso rewrite nella stessa lingua e negozio non sono distinguibili: viene mostrato il primo.

## Crediti

Idea e progetto: [OsaComunicare.it](https://www.osacomunicare.it)

## Licenza

Rilasciato con licenza [MIT](LICENSE).

## Contributi

Segnalazioni e pull request sono benvenute. Nelle segnalazioni indica la versione di PrestaShop, la versione di PHP, l'elenco degli override e dei moduli SEO installati e il messaggio di errore.
