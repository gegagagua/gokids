# ProCredit გადახდის ფლოუ — დოკუმენტაცია

## სქემა

```
მობაილ აპი / ვები
    │
    ▼
POST /api/procredit-payment  { card_id: 1 }
    │
    ▼  Backend:
    │  - ბარათის ქვეყნიდან იღებს tariff + currency
    │  - ქმნის ჩანაწერს bog_payments ცხრილში (status: pending)
    │  - იძახებს ProCredit Create Order (TLS + client cert)
    │  - იღებს bank order id, password, hppUrl
    │
    ▼  Response:
    {
      "success": true,
      "redirect_url": "https://3dss2test.quipu.de/flex?id=21490&password=rxcv...",
      "order_id": "PC_ABCD1234_1738350939"
    }
    │
    ▼
მომხმარებელი გადამისამართდება redirect_url-ზე (HPP)
    │  - მობაილში: WebView-ში იხსნება
    │  - ვებში: ბრაუზერში იხსნება
    │
    ▼
ბანკის HPP გვერდი (Quipu/ProCredit):
    │  მომხმარებელი შეაქვს: PAN, Expiry, CVV2, Name on Card
    │  ხდება 3DS ავთენტიფიკაცია (საჭიროების შემთხვევაში)
    │
    ▼  გადახდის შემდეგ:
HPP აბრუნებს მომხმარებელს hppRedirectUrl-ზე:
    /procredit-payment/success?order_id=PC_ABCD1234_1738350939
```

---

## გადახდის შემდეგ რა ხდება

### ვებზე (ბრაუზერი)

1. მომხმარებელი ხვდება `/procredit-payment/success?order_id=...` გვერდზე.
2. გვერდი ავტომატურად იძახებს: `GET /api/procredit-payment/status/{orderId}`.
3. Backend იძახებს **Get Order Details** ბანკთან (TLS-ით) და ამოწმებს სტატუსს.
4. გვერდი აჩვენებს შედეგს (completed / pending / failed).
5. **არაფერია დასამატებელი ვებზე** — ეს უკვე მუშაობს.

### მობაილ აპში (React Native / WebView)

1. `POST /api/procredit-payment` — იძახებთ API-ს, იღებთ `redirect_url` და `order_id`.
2. **WebView-ში** ხსნით `redirect_url`-ს (HPP გვერდი).
3. **WebView-ს ონავიგეშენ ლისენერი** უნდა ჰქონდეს — როცა URL შეიცავს `/procredit-payment/success`, ნიშნავს მომხმარებელმა გადახდა დაასრულა.
4. WebView-ს ხურავთ და იძახებთ: `GET /api/procredit-payment/status/{orderId}`.
5. პასუხში ამოწმებთ `status`:
   - `completed` → გადახდა წარმატებულია, `card_license` აქტიურია
   - `pending` → ჯერ კიდევ დამუშავების პროცესშია (შეგიძლიათ რამდენიმე წამის შემდეგ კიდევ მოთხოვოთ)
   - `failed` / `cancelled` → წარუმატებელი

**მობაილში დასამატებელი კოდის მაგალითი:**

```typescript
// 1. გადახდის დაწყება
const response = await api.post('/api/procredit-payment', { card_id: cardId });
const { redirect_url, order_id } = response.data;

// 2. WebView-ში HPP-ის გახსნა
// WebView onNavigationStateChange-ში:
const onNavigationChange = (navState) => {
  const url = navState.url;
  
  // success-ზე დაბრუნებისას
  if (url.includes('/procredit-payment/success')) {
    closeWebView();
    checkPaymentStatus(order_id);
  }
  
  // cancel-ზე
  if (url.includes('/procredit-payment/cancel')) {
    closeWebView();
    showMessage('გადახდა გაუქმებულია');
  }
};

// 3. სტატუსის შემოწმება
const checkPaymentStatus = async (orderId: string) => {
  const res = await api.get(`/api/procredit-payment/status/${orderId}`);
  
  if (res.data.status === 'completed') {
    // წარმატებულია! card_license უკვე გააქტიურებულია backend-ში
    showMessage('გადახდა წარმატებულია!');
    // card-ის ინფო განაახლეთ UI-ში
    refreshCardData();
  } else if (res.data.status === 'pending') {
    // ჯერ კიდევ პროცესშია — 2-3 წამის შემდეგ კიდევ სცადეთ
    setTimeout(() => checkPaymentStatus(orderId), 3000);
  } else {
    showMessage('გადახდა წარუმატებელია');
  }
};
```

---

## Backend — completed-ზე ავტომატურად ხდება

| # | მოქმედება | დეტალი |
|---|----------|--------|
| 1 | **Payment ჩანაწერი** | `payments` ცხრილში იქმნება (type: bank, status: completed) |
| 2 | **Dister ბალანსი** | განახლდება dister-ის და admin-ის ბალანსი პროცენტულად |
| 3 | **Card License** | ბარათზე აქტიურდება ლიცენზია: `{ type: "date", value: "2027-01-31" }` (1 წელი) |

---

## Status API

### Request

```
GET /api/procredit-payment/status/{orderId}
```

`orderId` = ჩვენი internal order_id (მაგ. `PC_ABCD1234_1738350939`), რომელიც Create Payment-ის response-ში მოდის.

### Response მაგალითი (completed)

```json
{
  "success": true,
  "order_id": "PC_ABCD1234_1738350939",
  "status": "completed",
  "amount": "10.00",
  "currency": "GEL",
  "paid_at": "2026-01-31T20:15:39+00:00",
  "bog_transaction_id": "21490",
  "card_id": 1,
  "card_license": { "type": "date", "value": "2027-01-31" }
}
```

### Response მაგალითი (pending)

```json
{
  "success": true,
  "order_id": "PC_ABCD1234_1738350939",
  "status": "pending",
  "amount": "10.00",
  "currency": "GEL",
  "paid_at": null,
  "bog_transaction_id": "21490",
  "card_id": 1,
  "card_license": { "type": "boolean", "value": false }
}
```

---

## API ენდპოინტები

| მეთოდი | URL | აღწერა |
|--------|-----|--------|
| `POST` | `/api/procredit-payment` | გადახდის შექმნა (body: `{ card_id }`) |
| `GET` | `/api/procredit-payment/status/{orderId}` | სტატუსის შემოწმება |
| `POST` | `/api/procredit-payment/callback` | ბანკის server-to-server callback |
| `POST` | `/api/procredit-payment/bulk` | bulk გადახდა (body: `{ garden_id, card_ids }`) |

---

## შეჯამება — რა უნდა დაამატოთ

| პლატფორმა | რა არის საჭირო |
|-----------|----------------|
| **Backend** | უკვე მზადაა — არაფერი |
| **ვები** | უკვე მზადაა — success გვერდი ავტომატურად ამოწმებს სტატუსს |
| **მობაილ აპი** | WebView + `onNavigationStateChange` + `GET /status/{orderId}` polling |
