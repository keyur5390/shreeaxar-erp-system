const ONES = [
  '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
  'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
  'Seventeen', 'Eighteen', 'Nineteen',
]

const TENS = [
  '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
]

function convertHundreds(number: number): string {
  const parts: string[] = []

  if (number >= 100) {
    parts.push(`${ONES[Math.floor(number / 100)]} Hundred`)
    number %= 100
  }

  if (number >= 20) {
    parts.push(TENS[Math.floor(number / 10)])
    number %= 10
  }

  if (number > 0) {
    parts.push(ONES[number])
  }

  return parts.join(' ').trim()
}

function convertWholeNumber(number: number): string {
  if (number === 0) return 'Zero'

  const parts: string[] = []
  const scales: Array<[number, string]> = [
    [1_000_000_000, 'Billion'],
    [1_000_000, 'Million'],
    [1_000, 'Thousand'],
  ]

  for (const [value, label] of scales) {
    if (number >= value) {
      const count = Math.floor(number / value)
      parts.push(`${convertHundreds(count)} ${label}`)
      number %= value
    }
  }

  if (number > 0) {
    parts.push(convertHundreds(number))
  }

  return parts.join(' ').trim()
}

function currencyName(currencyCode: string, plural: boolean): string {
  switch (currencyCode.toUpperCase()) {
    case 'USD':
      return plural ? 'US Dollars' : 'US Dollar'
    case 'EUR':
      return plural ? 'Euros' : 'Euro'
    case 'GBP':
      return plural ? 'Pounds Sterling' : 'Pound Sterling'
    case 'RWF':
      return plural ? 'Rwandan Francs' : 'Rwandan Franc'
    default:
      return currencyCode.toUpperCase()
  }
}

export function amountInWords(amount: number, currencyCode = 'RWF', decimalPlaces = 0): string {
  const isNegative = amount < 0
  const absolute = Math.abs(amount)
  const multiplier = 10 ** decimalPlaces
  let whole = Math.floor(absolute)
  let fraction = Math.round((absolute - whole) * multiplier)

  if (fraction >= multiplier) {
    whole += 1
    fraction = 0
  }

  let words = `${convertWholeNumber(whole)} ${currencyName(currencyCode, whole !== 1)}`

  if (decimalPlaces > 0) {
    words += ` and ${convertWholeNumber(fraction)} Cents`
  }

  return isNegative ? `Minus ${words}` : words
}
