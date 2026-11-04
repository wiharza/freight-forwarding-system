const { test, expect } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'http://localhost:8080';

async function login(page, email, password) {
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('domcontentloaded');
  await page.getByRole('textbox', { name: 'Email address' }).fill(email);
  await page.getByRole('textbox', { name: 'Password' }).fill(password);
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.waitForURL(`${BASE}/dashboard`, { timeout: 15000 });
  await page.waitForLoadState('domcontentloaded');
}

test.describe('Smoke test: Login -> Create Job -> Kalkulator PIB', () => {

  test('Budi (CS) login, buat Job valid, lalu kontainer ISO 6346 salah ditolak', async ({ page }) => {
    test.setTimeout(120000);
    // 1. Login sebagai CS (Budi)
    await login(page, 'budi@example.local', 'password');
    await expect(page).toHaveURL(`${BASE}/dashboard`);

    // 2. Buka Create Job
    await page.goto(`${BASE}/jobs/create`);
    await page.waitForLoadState('domcontentloaded');

    // Isi form Job valid
    await page.getByRole('textbox', { name: 'Nomor Bill of Lading (B/L)' }).fill('BL-SMOKE-001');
    await page.getByRole('combobox', { name: 'Pelabuhan Bongkar (POD)' }).selectOption({ index: 3 });
    await page.getByRole('combobox', { name: 'Kapal (dari Master — anti salah ketik)' }).selectOption({ index: 1 });

    // Kontainer VALID: check digit benar (MSKU1234565)
    await page.getByPlaceholder('Contoh: MSKU1234567').fill('MSKU1234565');
    await page.getByRole('combobox', { name: 'Ukuran' }).selectOption({ index: 0 });
    await page.getByRole('combobox', { name: 'Tipe' }).selectOption({ index: 0 });
    await page.getByPlaceholder('Seal No.').fill('SL-SMOKE001');
    await page.getByRole('spinbutton', { name: 'Berat (KG)' }).fill('21500');

    // Submit
    await page.getByRole('button', { name: 'Simpan & Buat Job' }).click();
    await page.waitForURL(/\/jobs\/\d+/, { timeout: 15000 });
    await page.waitForLoadState('domcontentloaded');

    // Verifikasi: tidak ada error merah, URL redirect ke job detail
    const redErrors = await page.locator('.text-red-600').allTextContents();
    expect(redErrors.filter(e => e.trim().length > 0)).toHaveLength(0);
    const url = page.url();
    expect(url).toMatch(/\/jobs\/\d+/);
    console.log('PASS: Job valid created, URL:', url);

    // 3. Coba buat Job dengan kontainer ISO 6346 SALAH
    await page.goto(`${BASE}/jobs/create`);
    await page.waitForLoadState('domcontentloaded');

    await page.getByRole('textbox', { name: 'Nomor Bill of Lading (B/L)' }).fill('BL-SMOKE-002');
    await page.getByRole('combobox', { name: 'Pelabuhan Bongkar (POD)' }).selectOption({ index: 3 });
    await page.getByRole('combobox', { name: 'Kapal (dari Master — anti salah ketik)' }).selectOption({ index: 1 });

    // Kontainer SALAH: MSKU1234567 (check digit seharusnya 5)
    await page.getByPlaceholder('Contoh: MSKU1234567').fill('MSKU1234567');
    await page.getByRole('combobox', { name: 'Ukuran' }).selectOption({ index: 0 });
    await page.getByRole('combobox', { name: 'Tipe' }).selectOption({ index: 0 });
    await page.getByPlaceholder('Seal No.').fill('SL-SMOKE002');
    await page.getByRole('spinbutton', { name: 'Berat (KG)' }).fill('21500');

    await page.getByRole('button', { name: 'Simpan & Buat Job' }).click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(6000);

    // Verifikasi: masih di halaman create (tidak redirect), dan ada error
    const urlAfter = page.url();
    console.log('URL after invalid submit:', urlAfter);
    expect(urlAfter).toContain('/jobs/create'); // harus tetap di form, tidak redirect

    // Cari SEMUA elemen yang mengandung kata error validasi
    const allText = await page.evaluate(() => document.body.innerText);
    console.log('Page contains "ISO":', allText.includes('ISO'));
    console.log('Page contains "check digit":', allText.includes('check digit'));
    console.log('Page contains "tidak valid":', allText.includes('tidak valid'));
    
    const redErrors2 = await page.locator('.text-red-600').allTextContents();
    console.log('Red errors:', JSON.stringify(redErrors2));
    
    // Error harus muncul (dari backend via Inertia error bag)
    const hasIsoError = allText.includes('ISO 6346') || allText.includes('check digit') || redErrors2.some(e => e.includes('ISO') || e.includes('check') || e.includes('valid'));
    expect(hasIsoError).toBeTruthy();
    console.log('PASS: Container ISO 6346 invalid rejected by UI');
  });
});
