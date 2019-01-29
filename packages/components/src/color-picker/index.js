/**
 * Parts of this source were derived and modified from react-color,
 * released under the MIT license.
 *
 * https://github.com/casesandberg/react-color/
 *
 * Copyright (c) 2015 Case Sandberg
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * External dependencies
 */
import classnames from 'classnames';
import { debounce, noop, partial } from 'lodash';

/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Alpha from './alpha';
import Hue from './hue';
import Inputs from './inputs';
import Saturation from './saturation';
import { colorToState, simpleCheckForValidColor, isValidHex } from './utils';

const toLowerCase = ( value ) => String( value ).toLowerCase();
const isValueEmpty = ( data ) => ( data.source === 'hex' && ! data.hex ) ||
	( data.source === 'rgb' && ( ! data.r || ! data.g || ! data.b ) ) ||
	( data.source === 'hsl' && ( ! data.h || ! data.s || ! data.l ) );
const isValidColor = ( data ) => data.hex ?
	isValidHex( data.hex ) :
	simpleCheckForValidColor( data );

export default class ColorPicker extends Component {
	constructor( { color = '0071a1' } ) {
		super( ...arguments );
		const colors = colorToState( color );
		this.state = {
			...colors,
			draftHex: toLowerCase( colors.hex ),
			draftRgb: colors.rgb,
			draftHsl: colors.hsl,
		};
		this.handleChange = this.handleChange.bind( this );
		this.handleInputChange = this.handleInputChange.bind( this );
	}

	handleChange( data ) {
		const { oldHue, onChangeComplete = noop } = this.props;
		const isValid = ! isValueEmpty( data ) && isValidColor( data );
		if ( isValid ) {
			const colors = colorToState( data, data.h || oldHue );
			this.setState( {
				...colors,
				draftHex: toLowerCase( colors.hex ),
				draftHsl: colors.hsl,
				draftRgb: colors.rgb,
			},
			debounce( partial( onChangeComplete, colors ), 100 )
			);
		}
	}

	handleInputChange( data ) {
		if ( data.state === 'commit' ) {
			this.handleChange( data );
		} else if ( data.state === 'draft' ) {
			if ( data.source === 'hex' ) {
				this.setState( {
					draftHex: toLowerCase( data.hex ),
				} );
			} else if ( data.source === 'rgb' ) {
				this.setState( {
					draftRgb: {
						r: data.r,
						g: data.g,
						b: data.b,
						a: data.a,
					},
				} );
			} else if ( data.source === 'hsl' ) {
				this.setState( {
					draftHsl: {
						h: data.h,
						s: data.s,
						l: data.l,
						a: data.a,
					},
				} );
			}
		}
	}

	render() {
		const { className, disableAlpha } = this.props;
		const {
			color,
			hsl,
			hsv,
			rgb,
			draftHex,
			draftHsl,
			draftRgb,
		} = this.state;
		const classes = classnames( className, {
			'components-color-picker': true,
			'is-alpha-disabled': disableAlpha,
			'is-alpha-enabled': ! disableAlpha,
		} );

		return (
			<div className={ classes }>
				<div className="components-color-picker__saturation">
					<Saturation
						hsl={ hsl }
						hsv={ hsv }
						onChange={ this.handleChange }
					/>
				</div>

				<div className="components-color-picker__body">
					<div className="components-color-picker__controls">
						<div className="components-color-picker__swatch">
							<div
								className="components-color-picker__active"
								style={ {
									backgroundColor: color && color.toRgbString(),
								} }
							/>
						</div>

						<div className="components-color-picker__toggles">
							<Hue hsl={ hsl } onChange={ this.handleChange } />
							{ disableAlpha ? null : (
								<Alpha
									rgb={ rgb }
									hsl={ hsl }
									onChange={ this.handleChange }
								/>
							) }
						</div>
					</div>

					<Inputs
						rgb={ draftRgb }
						hsl={ draftHsl }
						hex={ draftHex }
						onChange={ this.handleInputChange }
						disableAlpha={ disableAlpha }
					/>
				</div>
			</div>
		);
	}
}
