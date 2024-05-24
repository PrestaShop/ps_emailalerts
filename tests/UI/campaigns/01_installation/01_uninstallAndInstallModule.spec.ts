import { 
  test,
  expect,
  type Page,
  type BrowserContext,
} from '@playwright/test';

import { 
  boDashboardPage,
  boLoginPage,
  boModuleManagerPage,
  boProductsPage,
  dataModules,
  FakerProduct,
  foClassicCategoryPage,
  foClassicHomePage,
  foClassicProductPage,
  opsBOCatalogProduct,
  utilsFile,
  utilsTest,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'modules_ps_emailalerts_installation_uninstallAndInstallModule';

test.describe('Mail alerts module - Uninstall and install module', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  let idProduct: number;
  let nthProduct: number|null;

  const productOutOfStockNotAllowed: FakerProduct = new FakerProduct({
    name: 'Product Out of stock not allowed',
    type: 'standard',
    taxRule: 'No tax',
    tax: 0,
    quantity: 0,
    behaviourOutOfStock: 'Deny orders',
  });

  test.beforeAll(async ({ browser }) => {
    browserContext = await browser.newContext();
    page = await browserContext.newPage();
  });
  test.afterAll(async () => {
    await page.close();
  });

  test('PRE-Condition : Create product out of stock not allowed', async () => {
    await opsBOCatalogProduct.createProduct(page, productOutOfStockNotAllowed, `${baseContext}_preTest`);
  });

  // BackOffice - Fetch the ID of the product
  test('should login in BO', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'loginBO', baseContext);

    await boLoginPage.goTo(page, global.BO.URL);
    await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

    const pageTitle = await boDashboardPage.getPageTitle(page);
    expect(pageTitle).toContain(boDashboardPage.pageTitle);
  });

  test('should go to \'Catalog > Products\' page', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToProductsPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.catalogParentLink,
      boDashboardPage.productsLink,
    );

    const pageTitle = await boProductsPage.getPageTitle(page);
    expect(pageTitle).toContain(boProductsPage.pageTitle);
  });

  test('should filter list by \'product_name\'', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'filterProductName', baseContext);

    await boProductsPage.resetFilter(page);
    await boProductsPage.filterProducts(page, 'product_name', productOutOfStockNotAllowed.name, 'input');

    const numberOfProductsAfterFilter = await boProductsPage.getNumberOfProductsFromList(page);
    expect(numberOfProductsAfterFilter).toEqual(1);

    idProduct = await boProductsPage.getTextColumn(page, 'id_product', 1) as number;
  });

  // BackOffice - Uninstall Module
  test('should go to \'Modules > Module Manager\' page', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToModuleManagerPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.modulesParentLink,
      boDashboardPage.moduleManagerLink,
    );
    await boModuleManagerPage.closeSfToolBar(page);

    const pageTitle = await boModuleManagerPage.getPageTitle(page);
    expect(pageTitle).toContain(boModuleManagerPage.pageTitle);
  });

  test(`should search the module ${dataModules.psEmailAlerts.name}`, async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'searchModule', baseContext);

    const isModuleVisible = await boModuleManagerPage.searchModule(page, dataModules.psEmailAlerts);
    expect(isModuleVisible).toBeTruthy();
  });

  test('should display the uninstall modal and cancel it', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'uninstallModuleAndCancel', baseContext);

    const textResult = await boModuleManagerPage.setActionInModule(page, dataModules.psEmailAlerts, 'uninstall', true);
    expect(textResult).toEqual('');

    const isModuleVisible = await boModuleManagerPage.isModuleVisible(page, dataModules.psEmailAlerts);
    expect(isModuleVisible).toBeTruthy();

    const isModalVisible = await boModuleManagerPage.isModalActionVisible(page, dataModules.psEmailAlerts, 'uninstall');
    expect(isModalVisible).toBeFalsy();

    const dirExists = await utilsFile.doesFileExist(`${utilsFile.getRootPath()}/modules/${dataModules.psEmailAlerts.tag}/`);
    expect(dirExists).toBeTruthy();
  });

  test('should uninstall the module', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'uninstallModule', baseContext);

    const successMessage = await boModuleManagerPage.setActionInModule(page, dataModules.psEmailAlerts, 'uninstall', false);
    expect(successMessage).toEqual(boModuleManagerPage.uninstallModuleSuccessMessage(dataModules.psEmailAlerts.tag));

    // Check the directory `modules/Modules.psEmailAlerts.tag`
    const dirExists = await utilsFile.doesFileExist(`${utilsFile.getRootPath()}/modules/${dataModules.psEmailAlerts.tag}/`);
    expect(dirExists).toBeTruthy();
  });

  // FrontOffice - Check that the module is not present
  test('should go to Front Office', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToFoAfterUninstall', baseContext);

    page = await boModuleManagerPage.viewMyShop(page);
    await foClassicHomePage.changeLanguage(page, 'en');

    const isHomePage = await foClassicHomePage.isHomePage(page);
    expect(isHomePage).toBeTruthy();
  });

  test('should go to the All Products Page', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToAllProductsPageAfterUninstall', baseContext);

    await foClassicHomePage.goToAllProductsPage(page);

    const isCategoryPageVisible = await foClassicCategoryPage.isCategoryPage(page);
    expect(isCategoryPageVisible, 'Home category page was not opened').toBeTruthy();
  });

  test('should go the the second page', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToSecondPage', baseContext);

    await foClassicCategoryPage.goToNextPage(page);

    nthProduct = await foClassicCategoryPage.getNThChildFromIDProduct(page, idProduct);
    expect(nthProduct).not.toBeNull();
  });

  test('should go to the product page', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToProductPage', baseContext);

    await foClassicCategoryPage.goToProductPage(page, nthProduct!);

    const pageTitle = await foClassicProductPage.getPageTitle(page);
    expect(pageTitle.toUpperCase()).toContain(productOutOfStockNotAllowed.name.toUpperCase());

    const hasFlagOutOfStock = await foClassicProductPage.hasProductFlag(page, 'out_of_stock');
    expect(hasFlagOutOfStock).toBeTruthy();

    const hasBlockMailAlert = await foClassicProductPage.hasBlockMailAlert(page);
    expect(hasBlockMailAlert).toBeFalsy();
  });

  // BackOffice - Install the module
  test('should go back to BO', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goBackToBo', baseContext);

    page = await foClassicProductPage.closePage(browserContext, page, 0);
    await boModuleManagerPage.reloadPage(page);

    const pageTitle = await boModuleManagerPage.getPageTitle(page);
    expect(pageTitle).toContain(boModuleManagerPage.pageTitle);
  });

  test('should install the module', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'installModule', baseContext);

    const successMessage = await boModuleManagerPage.setActionInModule(page, dataModules.psEmailAlerts, 'install', false);
    expect(successMessage).toEqual(boModuleManagerPage.installModuleSuccessMessage(dataModules.psEmailAlerts.tag));

    // Check the directory `modules/Modules.psEmailAlerts.tag`
    const dirExists = await utilsFile.doesFileExist(`${utilsFile.getRootPath()}/modules/${dataModules.psEmailAlerts.tag}/`);
    expect(dirExists).toBeTruthy();
  });

  // FrontOffice - Check that the module is present
  test('should return to Front Office', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToFo', baseContext);

    page = await boModuleManagerPage.viewMyShop(page);
    await foClassicHomePage.changeLanguage(page, 'en');

    const isHomePage = await foClassicHomePage.isHomePage(page);
    expect(isHomePage).toBeTruthy();
  });

  test('should return to the All Products Page', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToAllProductsPage', baseContext);

    await foClassicHomePage.goToAllProductsPage(page);

    const isCategoryPageVisible = await foClassicCategoryPage.isCategoryPage(page);
    expect(isCategoryPageVisible, 'Home category page was not opened').toBeTruthy();
  });

  test('should return to the product page', async () => {
    await utilsTest.addContextItem(test.info(), 'testIdentifier', 'goToProductPageWithMailAlert', baseContext);

    await foClassicCategoryPage.goToNextPage(page);
    await foClassicCategoryPage.goToProductPage(page, nthProduct!);

    const pageTitle = await foClassicProductPage.getPageTitle(page);
    expect(pageTitle.toUpperCase()).toContain(productOutOfStockNotAllowed.name.toUpperCase());

    const hasFlagOutOfStock = await foClassicProductPage.hasProductFlag(page, 'out_of_stock');
    expect(hasFlagOutOfStock).toBeTruthy()

    const hasBlockMailAlert = await foClassicProductPage.hasBlockMailAlert(page);
    expect(hasBlockMailAlert).toBeTruthy();
  });

  test('POST-Condition : Delete product out of stock not allowed', async () => {
    await opsBOCatalogProduct.deleteProduct(page, productOutOfStockNotAllowed, `${baseContext}_postTest_0`);
  });
});
