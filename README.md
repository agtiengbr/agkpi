# AG KPIs for PrestaShop

AG KPIs is a PrestaShop module that adds custom order KPIs to the Back Office Orders page. It lets you create clickable KPI cards based on a single order status, define colors and time range, and place those cards in a dedicated panel below the native PrestaShop KPIs.

This module is useful for merchants and teams that want a faster way to monitor order flow, payment status, cancellations, and other order-related metrics directly from the Orders screen.

## Features

- Add custom KPI cards for the Orders page in the PrestaShop Back Office.
- Configure one order status per KPI.
- Define a custom title, background color, text color, and period in days.
- Display three metrics on each card: order count, total amount, and item count.
- Show custom KPIs in a separate panel below the native PrestaShop KPIs.
- Click a KPI card to open the Orders grid with the matching status and date filters applied.
- Enable, disable, edit, and delete KPIs from the module configuration page.

## Compatibility

- PrestaShop 8.0.0 or newer
- Tested in a PrestaShop 9 environment

## Module Information

- Module name: `agkpis`
- Display name: `AG KPIs`
- Author: `AGTI`
- Version: `1.0.0`

## Installation

### Option 1: Install from source in the modules directory

1. Copy the `agkpis` folder into your PrestaShop `modules/` directory.
2. Go to Back Office > Modules.
3. Search for `AG KPIs`.
4. Click Install.

### Option 2: Install from an existing project checkout

1. Make sure the module folder exists at `modules/agkpis`.
2. Open your PrestaShop Back Office.
3. Go to Back Office > Modules.
4. Find `AG KPIs` and install it.

## What Happens During Installation

When the module is installed, it:

- Creates its own database table for KPI definitions.
- Registers the Back Office assets for the Orders page and the module configuration page.
- Registers the hook that injects custom KPIs into the Orders KPI area.

## Configuration Tutorial

After installation:

1. Open Back Office > Modules.
2. Search for `AG KPIs`.
3. Click Configure.

On the configuration page, you can create a KPI with these fields:

- Title: the label shown on the card.
- Background color: the card background color in hexadecimal format, for example `#F6A623`.
- Text color: the text color in hexadecimal format, for example `#FFFFFF`.
- Period in days: how many recent days should be included in the KPI calculation.
- Position: display order inside the custom KPI panel.
- Order state: the single order status used by that KPI.
- Active: whether the KPI should be visible on the Orders page.

Click Save to create or update the KPI.

## How to Use the KPIs

Once configured, open Back Office > Orders.

You will see:

- The native PrestaShop KPI row at the top.
- A second panel called `Custom KPIs` rendered below it.
- One card for each active KPI.

Each card displays:

- Orders: number of orders in the selected time range and status.
- Total amount: total paid amount for those orders.
- Items: total quantity of order items.

## Clickable KPI Filtering

Every KPI card is clickable.

When you click a card, the module opens the Orders page with filters already applied:

- The selected order status filter.
- The date range based on the KPI period.

This makes it easier to move from a KPI summary to the actual order list behind that metric.

## KPI Management

From the module configuration page, you can manage KPIs with the built-in actions:

- Create a new KPI
- Edit an existing KPI
- Disable a KPI without deleting it
- Re-enable a disabled KPI
- Delete a KPI permanently

Disabled KPIs are kept in the database but are not shown on the Orders page.

## Example Use Cases

- Track paid orders in the last 30 days.
- Monitor canceled orders in the last 7 days.
- Watch waiting-for-payment orders in the current period.
- Build quick operational dashboards for finance or support teams.

## Uninstallation

If you uninstall the module, its database table is removed.

This means KPI definitions created by the module are deleted during uninstall.

## Troubleshooting

### The custom KPI panel does not appear

Check the following:

1. The module is installed and enabled.
2. At least one KPI exists and is marked as active.
3. You are viewing the Back Office Orders page.

### A KPI is visible in configuration but not on the Orders page

Check whether:

1. The KPI is active.
2. The order state is valid.
3. The period in days is greater than or equal to 1.

### Clicking a KPI does not filter as expected

The module applies the Orders grid filters through the Orders admin URL. If another customization overrides the Orders grid behavior, verify that the `osname` filter is still used for order state filtering in your project.

## SEO Notes

If you publish this module on a repository or internal catalog, these keyword phrases are relevant and naturally aligned with the module purpose:

- PrestaShop custom order KPIs
- PrestaShop Orders dashboard module
- PrestaShop Back Office KPI cards
- PrestaShop order status analytics
- PrestaShop admin order metrics

## License

Use the same licensing rules as the surrounding project unless your distribution process defines something else.