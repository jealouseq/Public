import { useEffect, useId, useRef, useState } from 'react';
import { ArrowCounterClockwise, MapTrifold, X } from '@phosphor-icons/react';
import type * as Leaflet from 'leaflet';
import { useI18n } from '../../src/lib/i18n';
import { useMotionPreference } from '../../src/lib/motion';
import { money } from '../../src/lib/rental';
import { classifyDeliveryPoint, deliveryTerms, type DeliveryFees, type DeliveryPointZone, type DeliveryZone, type DeliveryZones } from '../../src/lib/delivery-map';
import '../../src/delivery-map.css';

const zoneColors: Record<DeliveryZone, string> = { green: '#91aa92', yellow: '#d5b577', red: '#c38e83' };
const zoneOrder: DeliveryZone[] = ['green', 'yellow', 'red'];
type Selection = { zone: DeliveryPointZone; point: boolean } | null;

export function DeliveryZoneMap({ freeDeliveryFrom, greenFee, yellowFee }: DeliveryFees) {
  const { t, language } = useI18n();
  const titleId = useId();
  const helpId = useId();
  const opener = useRef<HTMLButtonElement>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  const mapElement = useRef<HTMLDivElement>(null);
  const map = useRef<Leaflet.Map | null>(null);
  const allBounds = useRef<Leaflet.LatLngBounds | null>(null);
  const zoneBounds = useRef<Partial<Record<DeliveryZone, Leaflet.LatLngBounds>>>({});
  const pointMarker = useRef<Leaflet.CircleMarker | null>(null);
  const [opened, setOpened] = useState(false);
  const [mapState, setMapState] = useState<'loading' | 'ready' | 'fallback' | 'error'>('loading');
  const [basemap, setBasemap] = useState<'loading' | 'ready' | 'unavailable'>('loading');
  const [data, setData] = useState<DeliveryZones | null>(null);
  const [selection, setSelection] = useState<Selection>(null);
  const reduced = useMotionPreference();
  const [buttonVisible, setButtonVisible] = useState(false);
  const [documentVisible, setDocumentVisible] = useState(() => !document.hidden);
  const fees = { freeDeliveryFrom, greenFee, yellowFee };
  const zoneName = (zone: DeliveryPointZone) => zone === 'green' ? t('Зелена зона', 'Зелёная зона') : zone === 'yellow' ? t('Жовта зона', 'Жёлтая зона') : zone === 'red' ? t('Червона зона', 'Красная зона') : zone === 'boundary' ? t('На межі зон', 'На границе зон') : t('Поза зонами', 'Вне зон');
  const price = (zone: DeliveryPointZone) => {
    const terms = deliveryTerms(zone, fees);
    return terms.kind === 'fixed' ? terms.fee == null ? t('Узгодимо вартість', 'Согласуем стоимость') : `${money(terms.fee)} грн` : terms.kind === 'taxi' ? t('за тарифом таксі', 'по тарифу такси') : t('За погодженням', 'По согласованию');
  };

  useEffect(() => {
    const observer = new IntersectionObserver(([entry]) => setButtonVisible(entry.isIntersecting));
    if (opener.current) observer.observe(opener.current);
    const updateVisibility = () => setDocumentVisible(!document.hidden);
    document.addEventListener('visibilitychange', updateVisibility);
    return () => { observer.disconnect(); document.removeEventListener('visibilitychange', updateVisibility); };
  }, []);

  useEffect(() => {
    if (!opened || !mapElement.current) return;
    const container = mapElement.current;
    let cancelled = false;
    let instance: Leaflet.Map | null = null;
    let resizeObserver: ResizeObserver | null = null;
    let tileTimer: ReturnType<typeof setTimeout> | undefined;
    setMapState('loading');
    setBasemap('loading');
    setSelection(null);

    async function initialize() {
      let geometry: DeliveryZones;
      try {
        geometry = (await import('../../wordpress/joyrent-rentals/data/delivery-zones.json')).default as DeliveryZones;
        if (cancelled) return;
        setData(geometry);
      } catch {
        if (!cancelled) setMapState('error');
        return;
      }

      try {
        const [L] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
        if (cancelled) return;
        instance = L.map(container, { zoomControl: false, scrollWheelZoom: true, keyboard: true, minZoom: 10, maxZoom: 18, fadeAnimation: false, zoomAnimation: false });
        map.current = instance;
        const currentMap = instance;
        currentMap.attributionControl.setPrefix(false);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 18,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
        });
        const localLabels = L.layerGroup([
          L.marker([46.4825, 30.7233], { interactive: false, keyboard: false, icon: L.divIcon({ className: 'zone-map-place', html: language === 'ru' ? 'Одесса' : 'Одеса', iconSize: [88, 24], iconAnchor: [44, 12] }) }),
        ]);
        let loadedTiles = 0;
        const showVectorBackground = () => {
          if (cancelled || loadedTiles > 0) return;
          setBasemap('unavailable');
          localLabels.addTo(currentMap);
        };
        tiles.on('tileerror', showVectorBackground);
        tiles.on('tileload', () => {
          if (cancelled) return;
          loadedTiles++;
          setBasemap('ready');
          localLabels.remove();
        });
        tiles.addTo(currentMap);
        tileTimer = setTimeout(showVectorBackground, 8000);

        const zones = L.geoJSON(geometry, {
          style: feature => ({ color: zoneColors[feature?.properties.zone as DeliveryZone], weight: 2, fillOpacity: 0.27, opacity: 1, interactive: false }),
        }).addTo(currentMap);
        allBounds.current = zones.getBounds();
        const bounds: Partial<Record<DeliveryZone, Leaflet.LatLngBounds>> = {};
        for (const zone of zoneOrder) {
          const matching = { type: 'FeatureCollection' as const, features: geometry.features.filter(feature => feature.properties.zone === zone) };
          bounds[zone] = L.geoJSON(matching).getBounds();
        }
        zoneBounds.current = bounds;
        L.control.zoom({ position: 'topright', zoomInTitle: t('Збільшити мапу', 'Увеличить карту'), zoomOutTitle: t('Зменшити мапу', 'Уменьшить карту') }).addTo(currentMap);
        L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(currentMap);
        currentMap.on('click', (event: Leaflet.LeafletMouseEvent) => {
          const zone = classifyDeliveryPoint([event.latlng.lng, event.latlng.lat], geometry);
          setSelection({ zone, point: true });
          pointMarker.current?.remove();
          pointMarker.current = L.circleMarker(event.latlng, { radius: 6, color: '#fff', fillColor: '#111315', fillOpacity: 1, weight: 2, interactive: false }).addTo(currentMap);
        });
        currentMap.invalidateSize();
        currentMap.fitBounds(zones.getBounds(), { padding: [32, 32], animate: false });
        resizeObserver = new ResizeObserver(() => currentMap.invalidateSize({ pan: false }));
        resizeObserver.observe(container);
        setMapState('ready');
      } catch {
        instance?.remove();
        instance = null;
        map.current = null;
        if (!cancelled) setMapState('fallback');
      }
    }

    void initialize();
    return () => {
      cancelled = true;
      clearTimeout(tileTimer);
      resizeObserver?.disconnect();
      instance?.remove();
      map.current = null;
      pointMarker.current = null;
      allBounds.current = null;
      zoneBounds.current = {};
    };
  }, [opened, language]);

  function selectZone(zone: DeliveryZone) {
    setSelection({ zone, point: false });
    pointMarker.current?.remove();
    pointMarker.current = null;
    const bounds = zoneBounds.current[zone];
    if (bounds) map.current?.fitBounds(bounds, { padding: [40, 40], animate: false, maxZoom: 13 });
  }

  function resetMap() {
    setSelection(null);
    pointMarker.current?.remove();
    pointMarker.current = null;
    if (allBounds.current) map.current?.fitBounds(allBounds.current, { padding: [32, 32], animate: false });
  }

  return <>
    <button ref={opener} type="button" className="delivery-map-open" data-motion={!reduced && buttonVisible && documentVisible && !opened ? 'running' : 'paused'} aria-haspopup="dialog" aria-expanded={opened} onClick={() => { dialog.current?.showModal(); setOpened(true); }}><span className="delivery-map-open-content"><MapTrifold size={19} weight="light" aria-hidden="true" />{t('Зони доставки', 'Зоны доставки')}</span></button>
    <dialog ref={dialog} className="delivery-map-dialog" aria-labelledby={titleId} aria-describedby={helpId} onClose={() => { setOpened(false); opener.current?.focus({ preventScroll: true }); }} onClick={event => {
      if (event.target !== event.currentTarget) return;
      const rect = event.currentTarget.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.current?.close();
    }}>
      <div className="delivery-map-heading"><div><p className="delivery-map-eyebrow">JOYRENT · {t('Одеса', 'Одесса')}</p><h2 id={titleId}>{t('Зони доставки', 'Зоны доставки')}</h2></div><button type="button" className="delivery-map-close" autoFocus aria-label={t('Закрити мапу', 'Закрыть карту')} onClick={() => dialog.current?.close()}><X size={22} /></button></div>
      <p className="delivery-map-help" id={helpId}>{t('Переміщуй мапу та змінюй масштаб. Торкнись точки, щоб перевірити її зону.', 'Перемещай карту и меняй масштаб. Нажми на точку, чтобы проверить её зону.')}<span>{t('З клавіатури: стрілки — рух, + / − — масштаб.', 'С клавиатуры: стрелки — движение, + / − — масштаб.')}</span></p>
      <div className="delivery-map-layout">
        <div className="delivery-map-stage">
          <div ref={mapElement} className={`delivery-map-canvas${mapState === 'fallback' || mapState === 'error' ? ' is-hidden' : ''}`} role="region" aria-label={t('Інтерактивна мапа зон доставки в Одесі', 'Интерактивная карта зон доставки в Одессе')} aria-describedby={helpId} />
          {mapState === 'loading' && <p className="delivery-map-loading" role="status">{t('Завантажуємо мапу…', 'Загружаем карту…')}</p>}
          {mapState === 'fallback' && data && <GeometryFallback data={data} selectZone={selectZone} zoneName={zoneName} />}
          {mapState === 'error' && <p className="delivery-map-load-error" role="status">{t('Не вдалося завантажити межі зон. Онови сторінку або уточни доставку під час підтвердження заявки.', 'Не удалось загрузить границы зон. Обнови страницу или уточни доставку при подтверждении заявки.')}</p>}
          {mapState === 'ready' && <button className="delivery-map-reset" type="button" onClick={resetMap}><ArrowCounterClockwise size={17} />{t('Усі зони', 'Все зоны')}</button>}
        </div>
        <div className="delivery-map-legend" aria-label={t('Умови доставки за зонами', 'Условия доставки по зонам')}>
          <p className="delivery-map-legend-title">{t('Доставка та повернення', 'Доставка и возврат')}</p>
          {zoneOrder.map(zone => <button key={zone} className={`delivery-map-zone delivery-map-zone-${zone}`} type="button" aria-pressed={selection?.zone === zone} onClick={() => selectZone(zone)}><span className="delivery-map-zone-name"><span aria-hidden="true" className="delivery-map-swatch" />{zoneName(zone)}</span><strong>{price(zone)}</strong></button>)}
          <p className="delivery-map-fee-note">{t('У вартість зони входять доставка та повернення консолі. Поза зонами — за погодженням.', 'В стоимость зоны входят доставка и обратный забор. Вне зон — по согласованию.')}</p>
          <p className="delivery-map-free">{t('При оренді від', 'При аренде от')} {freeDeliveryFrom} {t('днів доставка в зелену та жовту зони — безкоштовна.', 'дней доставка в зелёную и жёлтую зоны — бесплатно.')}</p>
          <p className="delivery-map-point" role="status" aria-live="polite">{selection ? <><strong>{selection.point ? `${t('Обрана точка', 'Выбранная точка')}: ` : ''}{zoneName(selection.zone)}</strong><span>{price(selection.zone)}{selection.zone === 'green' || selection.zone === 'yellow' ? t(' — доставка й повернення.', ' — доставка и возврат.') : ''}</span>{selection.zone === 'boundary' && <span>{t('Точну адресу на межі підтвердимо окремо.', 'Точный адрес на границе подтвердим отдельно.')}</span>}</> : t('Обери зону в списку або точку на мапі.', 'Выбери зону в списке или точку на карте.')}</p>
          <p className="delivery-map-confirm">{t('Поза зонами — за погодженням. Адресу й час підтвердимо перед орендою.', 'Вне зон — по согласованию. Адрес и время подтвердим перед арендой.')}</p>
        </div>
      </div>
      {(basemap === 'unavailable' && mapState === 'ready' || mapState === 'fallback') && <p className="delivery-map-offline" role="status">{t('Схема зон. Точну адресу підтвердимо перед орендою.', 'Схема зон. Точный адрес подтвердим перед арендой.')}</p>}
    </dialog>
  </>;
}

function GeometryFallback({ data, selectZone, zoneName }: { data: DeliveryZones; selectZone: (zone: DeliveryZone) => void; zoneName: (zone: DeliveryPointZone) => string }) {
  const project = ([lng, lat]: number[]) => [lng * Math.PI / 180, -Math.log(Math.tan(Math.PI / 4 + lat * Math.PI / 360))];
  const points = data.features.flatMap(feature => feature.geometry.coordinates.flat().map(project));
  const xs = points.map(point => point[0]), ys = points.map(point => point[1]);
  const minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys);
  const padding = (maxX - minX) * 0.13;
  const viewBox = [minX - padding, minY - padding, maxX - minX + padding * 2, maxY - minY + padding * 2].join(' ');
  return <svg className="delivery-map-fallback" viewBox={viewBox} aria-label={zoneName('green') + ', ' + zoneName('yellow') + ', ' + zoneName('red')}>
    {data.features.map(feature => <path key={feature.properties.id} d={feature.geometry.coordinates.map(ring => ring.map((point, index) => `${index ? 'L' : 'M'}${project(point).join(' ')}`).join(' ') + ' Z').join(' ')} fill={zoneColors[feature.properties.zone]} fillOpacity=".27" stroke={zoneColors[feature.properties.zone]} strokeWidth="2" vectorEffect="non-scaling-stroke" fillRule="evenodd" role="button" tabIndex={0} aria-label={zoneName(feature.properties.zone)} onClick={() => selectZone(feature.properties.zone)} onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectZone(feature.properties.zone); } }} />)}
  </svg>;
}
